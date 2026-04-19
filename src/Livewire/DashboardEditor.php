<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseKpi;

class DashboardEditor extends Component
{
    public ?int $dashboardId = null;
    public string $name = '';
    public string $description = '';
    public string $icon = 'squares-2x2';
    public array $selectedKpiIds = [];

    public function mount(?DatawarehouseDashboard $dashboard = null): void
    {
        $user = Auth::user();

        if ($dashboard && $dashboard->exists) {
            abort_unless($dashboard->team_id === $user->currentTeam->id, 403);

            $this->dashboardId = $dashboard->id;
            $this->name = $dashboard->name;
            $this->description = $dashboard->description ?? '';
            $this->icon = $dashboard->icon;
            $this->selectedKpiIds = $dashboard->kpis()
                ->orderByPivot('position')
                ->pluck('datawarehouse_kpis.id')
                ->toArray();
        }
    }

    public function addKpi(int $kpiId): void
    {
        if (!in_array($kpiId, $this->selectedKpiIds)) {
            $this->selectedKpiIds[] = $kpiId;
        }
    }

    public function removeKpi(int $kpiId): void
    {
        $this->selectedKpiIds = array_values(array_filter(
            $this->selectedKpiIds,
            fn ($id) => $id !== $kpiId,
        ));
    }

    public function moveKpiUp(int $index): void
    {
        if ($index <= 0 || $index >= count($this->selectedKpiIds)) {
            return;
        }

        [$this->selectedKpiIds[$index - 1], $this->selectedKpiIds[$index]] =
            [$this->selectedKpiIds[$index], $this->selectedKpiIds[$index - 1]];
    }

    public function moveKpiDown(int $index): void
    {
        if ($index < 0 || $index >= count($this->selectedKpiIds) - 1) {
            return;
        }

        [$this->selectedKpiIds[$index], $this->selectedKpiIds[$index + 1]] =
            [$this->selectedKpiIds[$index + 1], $this->selectedKpiIds[$index]];
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        if ($this->dashboardId) {
            $dashboard = DatawarehouseDashboard::forTeam($team->id)->findOrFail($this->dashboardId);
            $dashboard->update([
                'name'        => $this->name,
                'description' => $this->description ?: null,
                'icon'        => $this->icon,
            ]);
        } else {
            $maxPosition = DatawarehouseDashboard::forTeam($team->id)->max('position') ?? 0;
            $dashboard = DatawarehouseDashboard::create([
                'team_id'     => $team->id,
                'user_id'     => $user->id,
                'name'        => $this->name,
                'description' => $this->description ?: null,
                'icon'        => $this->icon,
                'position'    => $maxPosition + 1,
            ]);
        }

        // Sync pivot with positions
        $syncData = [];
        foreach ($this->selectedKpiIds as $position => $kpiId) {
            $syncData[$kpiId] = ['position' => $position];
        }
        $dashboard->kpis()->sync($syncData);

        $this->redirect(route('datawarehouse.dashboard.view', $dashboard));
    }

    #[Computed]
    public function availableKpis()
    {
        $team = Auth::user()->currentTeam;

        return DatawarehouseKpi::forTeam($team->id)
            ->whereIn('status', ['active', 'draft'])
            ->whereNotIn('id', $this->selectedKpiIds ?: [0])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedKpis()
    {
        if (empty($this->selectedKpiIds)) {
            return collect();
        }

        $kpis = DatawarehouseKpi::whereIn('id', $this->selectedKpiIds)->get()->keyBy('id');

        return collect($this->selectedKpiIds)
            ->map(fn ($id) => $kpis->get($id))
            ->filter();
    }

    public function render()
    {
        return view('datawarehouse::livewire.dashboard-editor')
            ->layout('platform::layouts.app');
    }
}
