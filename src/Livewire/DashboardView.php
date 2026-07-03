<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Services\KpiQueryBuilder;

class DashboardView extends Component
{
    public DatawarehouseDashboard $dashboard;
    public bool $confirmDelete = false;

    public function mount(DatawarehouseDashboard $dashboard): void
    {
        $user = Auth::user();
        abort_unless($dashboard->team_id === $user->currentTeam->id, 403);

        $this->dashboard = $dashboard;
    }

    public function confirmDeleteDashboard(): void
    {
        $this->confirmDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = false;
    }

    public function deleteDashboard(): void
    {
        // Custom-view dashboards (e.g. RKV) are system views and get re-created;
        // don't allow deleting them here.
        if ($this->dashboard->isCustomView()) {
            return;
        }

        $this->dashboard->delete();
        $this->redirect(route('datawarehouse.dashboard'));
    }

    public function render()
    {
        // Custom view (e.g. RKV forecast): render the registered partial fed by
        // its service, instead of the KPI-tile grid.
        if ($this->dashboard->isCustomView() && ($viewCfg = $this->dashboard->viewConfig())) {
            $user = Auth::user();
            $viewData = app($viewCfg['service'])->compute($user->currentTeam->id, $user->id);

            return view('datawarehouse::livewire.dashboard-view', [
                'kpis'      => collect(),
                'viewCfg'   => $viewCfg,
                'viewData'  => $viewData,
            ])->layout('platform::layouts.app');
        }

        $kpis = $this->dashboard->kpis()
            ->whereIn('datawarehouse_kpis.status', ['active', 'draft', 'error'])
            ->get();

        // Lazy cache refresh (max 5 per load)
        $builder = new KpiQueryBuilder();
        $refreshed = 0;
        foreach ($kpis as $kpi) {
            if (!$kpi->is_group && $kpi->status !== 'draft' && !$kpi->isCacheValid() && $refreshed < 5) {
                try {
                    $builder->executeAndCache($kpi);
                    $refreshed++;
                } catch (\Throwable) {
                    // Keep stale value
                }
            }
        }

        return view('datawarehouse::livewire.dashboard-view', [
            'kpis'     => $kpis,
            'viewCfg'  => null,
            'viewData' => null,
        ])->layout('platform::layouts.app');
    }
}
