<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseDashboardPanel;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Services\PanelConfigValidator;

class DashboardEditor extends Component
{
    public ?int $dashboardId = null;
    public string $name = '';
    public string $description = '';
    public string $icon = 'squares-2x2';
    public array $selectedKpiIds = [];

    /**
     * Editor rows for panels. Each: {type, title, kpi_id, kpi_ids[], granularity,
     * stack_children}. Mapped to/from the stored panel config on load/save.
     */
    public array $panels = [];
    public string $newPanelType = 'kpi_value';

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

            $this->panels = $dashboard->panels()->get()->map(function ($panel) {
                $c = $panel->config ?? [];
                return [
                    'type'           => $panel->type,
                    'title'          => $panel->title ?? '',
                    'kpi_id'         => $c['kpi_id'] ?? null,
                    'kpi_ids'        => $panel->type === 'progress'
                        ? array_values(array_map(fn ($it) => (int) ($it['kpi_id'] ?? 0), $c['items'] ?? []))
                        : array_values(array_map('intval', $c['kpi_ids'] ?? [])),
                    'granularity'    => $c['granularity'] ?? 'month',
                    'stack_children' => (bool) ($c['stack_children'] ?? false),
                ];
            })->toArray();
        }
    }

    public function addPanel(): void
    {
        $this->panels[] = [
            'type'           => in_array($this->newPanelType, ['kpi_value', 'kpi_chart', 'progress', 'summary'], true) ? $this->newPanelType : 'kpi_value',
            'title'          => '',
            'kpi_id'         => null,
            'kpi_ids'        => [],
            'granularity'    => 'month',
            'stack_children' => false,
        ];
    }

    public function removePanel(int $i): void
    {
        unset($this->panels[$i]);
        $this->panels = array_values($this->panels);
    }

    public function movePanel(int $i, int $dir): void
    {
        $j = $i + $dir;
        if ($j < 0 || $j >= count($this->panels)) {
            return;
        }
        [$this->panels[$i], $this->panels[$j]] = [$this->panels[$j], $this->panels[$i]];
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

        // Panels: validate each, then rebuild (delete + recreate in order).
        $validator = app(PanelConfigValidator::class);
        $built = [];
        foreach (array_values($this->panels) as $i => $panel) {
            $type = $panel['type'] ?? '';
            $config = $this->buildPanelConfig($panel);
            if ($err = $validator->validate($type, $config, $team->id)) {
                $this->addError('panels', 'Panel #' . ($i + 1) . ': ' . $err);
                return;
            }
            $built[] = [
                'type'     => $type,
                'title'    => ($panel['title'] ?? '') !== '' ? $panel['title'] : null,
                'config'   => $config,
                'position' => $i,
            ];
        }
        $dashboard->panels()->delete();
        foreach ($built as $b) {
            $dashboard->panels()->create($b);
        }

        $this->redirect(route('datawarehouse.dashboard.view', $dashboard));
    }

    /** Map an editor panel row to the stored config shape for its type. */
    private function buildPanelConfig(array $panel): array
    {
        return match ($panel['type'] ?? '') {
            'kpi_value' => ['kpi_id' => (int) ($panel['kpi_id'] ?? 0)],
            'kpi_chart' => [
                'kpi_id'         => (int) ($panel['kpi_id'] ?? 0),
                'granularity'    => in_array($panel['granularity'] ?? 'month', ['month', 'quarter'], true) ? $panel['granularity'] : 'month',
                'stack_children' => (bool) ($panel['stack_children'] ?? false),
            ],
            'progress' => ['items' => array_values(array_map(fn ($id) => ['kpi_id' => (int) $id], array_filter($panel['kpi_ids'] ?? [])))],
            'summary'  => ['kpi_ids' => array_values(array_map('intval', array_filter($panel['kpi_ids'] ?? [])))],
            default    => [],
        };
    }

    /** All team KPIs (id + name), for the panel KPI pickers. */
    #[Computed]
    public function allKpis()
    {
        return DatawarehouseKpi::forTeam(Auth::user()->currentTeam->id)
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('name')
            ->get(['id', 'name']);
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
