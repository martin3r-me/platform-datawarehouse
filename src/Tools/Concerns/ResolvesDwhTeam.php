<?php

namespace Platform\Datawarehouse\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;

trait ResolvesDwhTeam
{
    /**
     * Resolves team_id from arguments or ToolContext and checks membership.
     *
     * @return array{team_id: int|null, team: Team|null, error: ToolResult|null}
     */
    protected function resolveTeam(array $arguments, ToolContext $context): array
    {
        $teamId = $arguments['team_id'] ?? $context->team?->id;
        if ($teamId === 0 || $teamId === '0') {
            $teamId = null;
        }

        if (!$teamId) {
            return [
                'team_id' => null,
                'team' => null,
                'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze "core.teams.GET" um verfügbare Teams zu sehen.'),
            ];
        }

        $team = Team::find((int)$teamId);
        if (!$team) {
            return [
                'team_id' => (int)$teamId,
                'team' => null,
                'error' => ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden. Nutze "core.teams.GET" um verfügbare Teams zu sehen.'),
            ];
        }

        if (!$context->user) {
            return [
                'team_id' => (int)$teamId,
                'team' => $team,
                'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.'),
            ];
        }

        $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
        if (!$userHasAccess) {
            return [
                'team_id' => (int)$teamId,
                'team' => $team,
                'error' => ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team. Nutze "core.teams.GET" um verfügbare Teams zu sehen.'),
            ];
        }

        return ['team_id' => (int)$teamId, 'team' => $team, 'error' => null];
    }
}
