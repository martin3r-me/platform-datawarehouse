<?php

namespace Platform\Datawarehouse\Tools\Concerns;

use Platform\Datawarehouse\Models\DatawarehouseKpi;

/**
 * Shared validation for the KPI drill-down hierarchy (parent_kpi_id).
 * Used by both CreateKpiTool and UpdateKpiTool.
 */
trait ValidatesKpiHierarchy
{
    /**
     * Validate a candidate parent_kpi_id. Returns an error string or null.
     *
     * @param  int|null               $parentId  The proposed parent (null = top-level / detach).
     * @param  int                     $teamId    The KPI's team — parent must belong to it.
     * @param  DatawarehouseKpi|null   $self      The KPI being updated (null on create), for
     *                                            self-reference and cycle detection.
     */
    protected function validateKpiParent(?int $parentId, int $teamId, ?DatawarehouseKpi $self = null): ?string
    {
        if ($parentId === null) {
            return null;
        }

        if ($self !== null && $parentId === (int) $self->id) {
            return 'Ein KPI kann nicht sein eigenes Eltern-Element sein.';
        }

        $parent = DatawarehouseKpi::query()->forTeam($teamId)->find($parentId);
        if (!$parent) {
            return "Eltern-KPI {$parentId} nicht gefunden (oder kein Zugriff).";
        }

        // Cycle detection: walking up from the proposed parent must never
        // reach $self, otherwise the hierarchy would loop.
        if ($self !== null) {
            $cursor = $parent;
            $guard = 0;
            while ($cursor !== null && $guard++ < 100) {
                if ((int) $cursor->id === (int) $self->id) {
                    return 'Ungültige Hierarchie: das würde einen Zyklus erzeugen (das gewählte Eltern-Element ist ein Nachfahre dieses KPI).';
                }
                $cursor = $cursor->parent_kpi_id ? $parent->newQuery()->find($cursor->parent_kpi_id) : null;
            }
        }

        return null;
    }
}
