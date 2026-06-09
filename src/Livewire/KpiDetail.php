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

    public bool $showDeleteModal = false;

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

    public function openDeleteModal(): void
    {
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        // Detach pivot rows so deleted KPIs don't leave orphans on dashboards.
        // Snapshots stay (cascade on hard-delete; harmless under soft-delete).
        $this->kpi->dashboards()->detach();
        $this->kpi->delete();

        $this->redirect(route('datawarehouse.dashboard'));
    }

    /**
     * Child KPIs for drill-down (empty for leaf KPIs).
     */
    #[Computed]
    public function children()
    {
        return $this->kpi->children()->get();
    }

    /**
     * Parent KPI for the "up one level" link (null for top-level KPIs).
     */
    #[Computed]
    public function parentKpi(): ?DatawarehouseKpi
    {
        return $this->kpi->parent_kpi_id
            ? DatawarehouseKpi::find($this->kpi->parent_kpi_id)
            : null;
    }

    /**
     * Quarter + month breakdown for the time chart (empty without a date column).
     */
    #[Computed]
    public function breakdown(): array
    {
        if (!$this->kpi->hasDateColumn()) {
            return ['quarters' => [], 'months' => []];
        }

        $builder = new KpiQueryBuilder();

        try {
            return [
                'quarters' => $builder->executeBreakdown($this->kpi, 'quarter'),
                'months'   => $builder->executeBreakdown($this->kpi, 'month'),
            ];
        } catch (\Throwable) {
            return ['quarters' => [], 'months' => []];
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
