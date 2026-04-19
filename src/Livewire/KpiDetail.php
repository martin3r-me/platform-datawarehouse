<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseKpiSnapshot;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\KpiQueryBuilder;

class KpiDetail extends Component
{
    public DatawarehouseKpi $kpi;
    public array $rangeValues = [];
    public bool $rangesLoaded = false;
    public bool $rangesLoading = false;

    public function mount(DatawarehouseKpi $kpi): void
    {
        $user = Auth::user();
        abort_unless($kpi->team_id === $user->currentTeam->id, 403);

        $this->kpi = $kpi;
    }

    public function loadRanges(): void
    {
        if (!$this->kpi->hasDateColumn()) {
            return;
        }

        $this->rangesLoading = true;

        try {
            $builder = new KpiQueryBuilder();
            $this->rangeValues = $builder->executeAllRanges($this->kpi);
            $this->rangesLoaded = true;
        } catch (\Throwable $e) {
            $this->rangeValues = [];
            $this->rangesLoaded = true;
        }

        $this->rangesLoading = false;
    }

    public function recalculate(): void
    {
        try {
            $builder = new KpiQueryBuilder();
            $builder->executeAndCache($this->kpi, 'manual');
            $this->kpi->refresh();
        } catch (\Throwable) {
            $this->kpi->refresh();
        }
    }

    #[Computed]
    public function snapshots()
    {
        return DatawarehouseKpiSnapshot::where('kpi_id', $this->kpi->id)
            ->orderByDesc('calculated_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function streamNames(): array
    {
        $names = [];
        $streams = $this->kpi->definition['streams'] ?? [];

        foreach ($streams as $streamDef) {
            $stream = DatawarehouseStream::find($streamDef['stream_id']);
            $names[$streamDef['alias']] = $stream?->name ?? 'Stream #' . $streamDef['stream_id'];
        }

        return $names;
    }

    public function render()
    {
        return view('datawarehouse::livewire.kpi-detail')
            ->layout('platform::layouts.app');
    }
}
