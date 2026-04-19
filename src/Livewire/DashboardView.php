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
        $this->dashboard->delete();
        $this->redirect(route('datawarehouse.dashboard'));
    }

    public function render()
    {
        $kpis = $this->dashboard->kpis()
            ->whereIn('datawarehouse_kpis.status', ['active', 'draft', 'error'])
            ->get();

        // Lazy cache refresh (max 5 per load)
        $builder = new KpiQueryBuilder();
        $refreshed = 0;
        foreach ($kpis as $kpi) {
            if ($kpi->status !== 'draft' && !$kpi->isCacheValid() && $refreshed < 5) {
                try {
                    $builder->executeAndCache($kpi);
                    $refreshed++;
                } catch (\Throwable) {
                    // Keep stale value
                }
            }
        }

        return view('datawarehouse::livewire.dashboard-view', [
            'kpis' => $kpis,
        ])->layout('platform::layouts.app');
    }
}
