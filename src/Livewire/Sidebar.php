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
        $allKpis = DatawarehouseKpi::forTeam($teamId)
            ->whereIn('status', $kpiStatuses)
            ->orderBy('position')
            ->get();

        $byParent = [];
        foreach ($allKpis as $k) {
            $byParent[$k->parent_kpi_id ?? 0][] = $k;
        }
        $kpiTree = $this->buildKpiTree($byParent, 0);

        // Ensure registered custom views (e.g. RKV) exist as dashboards for this
        // team, so they appear in the list under /dashboards/{id} like the rest.
        DatawarehouseDashboard::ensureRegisteredViews($teamId, $user->id);

        $dashboards = DatawarehouseDashboard::forTeam($teamId)
            ->orderBy('position')
            ->get();

        return view('datawarehouse::livewire.sidebar', [
            'systemStreams' => $systemStreams,
            'userStreams'   => $userStreams,
            'kpiTree'       => $kpiTree,
            'dashboards'   => $dashboards,
        ]);
    }

    /**
     * Build a nested KPI tree for the sidebar. Each node carries its own
     * lowercased name plus a flat list of all descendant names, so the
     * client-side search can show/auto-expand any branch with a match.
     *
     * @return array<int, array{kpi: DatawarehouseKpi, self_lower: string, desc_lower: array<int,string>, children: array}>
     */
    private function buildKpiTree(array $byParent, int $parentId, array $ancestorLower = []): array
    {
        $nodes = $byParent[$parentId] ?? [];

        return array_map(function (DatawarehouseKpi $kpi) use ($byParent, $ancestorLower) {
            $selfLower = mb_strtolower($kpi->name);
            $children = $this->buildKpiTree($byParent, $kpi->id, array_merge($ancestorLower, [$selfLower]));

            $descLower = [];
            foreach ($children as $child) {
                $descLower[] = $child['self_lower'];
                $descLower = array_merge($descLower, $child['desc_lower']);
            }

            // Searchable haystack = self + ancestors + descendants, so a hit shows
            // the matching node, its path up, and its whole subtree.
            $haystack = array_values(array_unique(array_merge([$selfLower], $ancestorLower, $descLower)));

            return [
                'kpi'        => $kpi,
                'self_lower' => $selfLower,
                'desc_lower' => $descLower,
                'haystack'   => $haystack,
                'children'   => $children,
            ];
        }, $nodes);
    }
}
