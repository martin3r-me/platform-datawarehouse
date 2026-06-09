<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('datawarehouse::livewire.sidebar', [
                'systemStreams' => collect(),
                'userStreams'   => collect(),
                'kpis'         => collect(),
                'dashboards'   => collect(),
            ]);
        }

        $teamId = $user->currentTeam->id;

        $systemStreams = DatawarehouseStream::where('team_id', $teamId)
            ->system()
            ->whereIn('status', ['active', 'onboarding'])
            ->orderBy('name')
            ->get();

        $userStreams = DatawarehouseStream::where('team_id', $teamId)
            ->userCreated()
            ->whereIn('status', ['active', 'onboarding'])
            ->orderBy('name')
            ->get();

        $kpiStatuses = ['active', 'draft', 'error'];
        $kpis = DatawarehouseKpi::forTeam($teamId)
            ->whereIn('status', $kpiStatuses)
            ->whereNull('parent_kpi_id')
            ->with(['children' => fn ($q) => $q->whereIn('status', $kpiStatuses)->orderBy('position')])
            ->orderBy('position')
            ->get();

        $dashboards = DatawarehouseDashboard::forTeam($teamId)
            ->orderBy('position')
            ->get();

        return view('datawarehouse::livewire.sidebar', [
            'systemStreams' => $systemStreams,
            'userStreams'   => $userStreams,
            'kpis'         => $kpis,
            'dashboards'   => $dashboards,
        ]);
    }
}
