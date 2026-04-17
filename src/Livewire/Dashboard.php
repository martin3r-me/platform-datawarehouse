<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\KpiQueryBuilder;

class Dashboard extends Component
{
    #[On('datawarehouse:stream-created')]
    public function refreshStreams(): void
    {
        // Re-renders automatically
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $streams = DatawarehouseStream::where('team_id', $team->id)
            ->withCount('imports')
            ->orderBy('name')
            ->get();

        $stats = [
            'total'      => $streams->count(),
            'active'     => $streams->where('status', 'active')->count(),
            'onboarding' => $streams->where('status', 'onboarding')->count(),
            'success'    => $streams->where('last_status', 'success')->count(),
            'error'      => $streams->where('last_status', 'error')->count(),
        ];

        // Load KPIs and refresh stale caches (max 5 per page load)
        $kpis = DatawarehouseKpi::forTeam($team->id)
            ->active()
            ->orderBy('position')
            ->get();

        $builder = new KpiQueryBuilder();
        $refreshed = 0;
        foreach ($kpis as $kpi) {
            if (!$kpi->isCacheValid() && $refreshed < 5) {
                try {
                    $builder->executeAndCache($kpi);
                    $refreshed++;
                } catch (\Throwable) {
                    // Keep stale value or null — error is stored on the model
                }
            }
        }

        return view('datawarehouse::livewire.dashboard', [
            'streams' => $streams,
            'stats'   => $stats,
            'kpis'    => $kpis,
        ])->layout('platform::layouts.app');
    }
}
