<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\KpiQueryBuilder;
use Platform\Datawarehouse\Services\SystemStreamProvisioner;

class Dashboard extends Component
{
    public ?int $confirmDeleteKpiId = null;

    #[On('datawarehouse:stream-created')]
    public function refreshStreams(): void
    {
        // Re-renders automatically
    }

    public function confirmDeleteKpi(int $id): void
    {
        $this->confirmDeleteKpiId = $id;
    }

    public function cancelDeleteKpi(): void
    {
        $this->confirmDeleteKpiId = null;
    }

    public function deleteKpi(): void
    {
        if (!$this->confirmDeleteKpiId) {
            return;
        }

        $team = Auth::user()->currentTeam;
        $kpi = DatawarehouseKpi::forTeam($team->id)->find($this->confirmDeleteKpiId);

        if ($kpi) {
            $kpi->delete();
        }

        $this->confirmDeleteKpiId = null;
    }

    public function duplicateKpi(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $kpi = DatawarehouseKpi::forTeam($team->id)->find($id);

        if (!$kpi) {
            return;
        }

        $maxPosition = DatawarehouseKpi::forTeam($team->id)->max('position') ?? 0;

        DatawarehouseKpi::create([
            'team_id'    => $kpi->team_id,
            'user_id'    => Auth::id(),
            'name'       => $kpi->name . ' (Kopie)',
            'icon'       => $kpi->icon,
            'variant'    => $kpi->variant,
            'unit'       => $kpi->unit,
            'format'     => $kpi->format,
            'decimals'   => $kpi->decimals,
            'position'   => $maxPosition + 1,
            'definition' => $kpi->definition,
            'status'     => 'draft',
        ]);
    }

    public function moveKpiUp(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $kpis = DatawarehouseKpi::forTeam($team->id)
            ->whereIn('status', ['active', 'draft', 'error'])
            ->orderBy('position')
            ->get();

        $index = $kpis->search(fn ($k) => $k->id === $id);
        if ($index === false || $index === 0) {
            return;
        }

        $current = $kpis[$index];
        $prev = $kpis[$index - 1];

        $tempPos = $current->position;
        $current->update(['position' => $prev->position]);
        $prev->update(['position' => $tempPos]);
    }

    public function moveKpiDown(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $kpis = DatawarehouseKpi::forTeam($team->id)
            ->whereIn('status', ['active', 'draft', 'error'])
            ->orderBy('position')
            ->get();

        $index = $kpis->search(fn ($k) => $k->id === $id);
        if ($index === false || $index === $kpis->count() - 1) {
            return;
        }

        $current = $kpis[$index];
        $next = $kpis[$index + 1];

        $tempPos = $current->position;
        $current->update(['position' => $next->position]);
        $next->update(['position' => $tempPos]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Lazy-provision system streams if any are missing
        $systemCount = DatawarehouseStream::forTeam($team->id)->system()->count();
        if ($systemCount < count(SystemStreamProvisioner::SYSTEM_PROVIDERS)) {
            app(SystemStreamProvisioner::class)->ensureForTeam($team->id);
        }

        $allStreams = DatawarehouseStream::where('team_id', $team->id)
            ->withCount('imports')
            ->orderBy('name')
            ->get();

        $systemStreams = $allStreams->where('is_system', true);
        $userStreams = $allStreams->where('is_system', false);

        $stats = [
            'total'      => $userStreams->count(),
            'active'     => $userStreams->where('status', 'active')->count(),
            'onboarding' => $userStreams->where('status', 'onboarding')->count(),
            'success'    => $userStreams->where('last_status', 'success')->count(),
            'error'      => $userStreams->where('last_status', 'error')->count(),
        ];

        // Load KPIs (active, draft, error) and refresh stale caches (max 5 per page load)
        $kpis = DatawarehouseKpi::forTeam($team->id)
            ->whereIn('status', ['active', 'draft', 'error'])
            ->orderBy('position')
            ->get();

        $builder = new KpiQueryBuilder();
        $refreshed = 0;
        foreach ($kpis as $kpi) {
            if ($kpi->status !== 'draft' && !$kpi->isCacheValid() && $refreshed < 5) {
                try {
                    $builder->executeAndCache($kpi);
                    $refreshed++;
                } catch (\Throwable) {
                    // Keep stale value or null — error is stored on the model
                }
            }
        }

        return view('datawarehouse::livewire.dashboard', [
            'systemStreams' => $systemStreams,
            'userStreams'   => $userStreams,
            'streams'       => $userStreams,
            'stats'         => $stats,
            'kpis'          => $kpis,
        ])->layout('platform::layouts.app');
    }
}
