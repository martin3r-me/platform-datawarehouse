<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Lists a stream's exclusion rules — rows matching ANY rule are removed from
 * every KPI calculation on that stream ("bereinigt"). Read-only.
 */
class GetStreamExclusionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_exclusions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/streams/{id}/exclusions - Liefert die Ausschluss-Regeln eines Streams. Zeilen, die IRGENDEINE Regel erfüllen, zählen in KEINER KPI dieses Streams mit. Regel-Form: { field, op (contains|equals|lt|lte|gt|gte|empty), value, note }. ERFORDERLICH: stream_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'stream_id' => ['type' => 'integer', 'description' => 'ID des Streams (ERFORDERLICH).'],
            ],
            'required' => ['stream_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $found = $this->validateAndFindModel($arguments, $context, 'stream_id', DatawarehouseStream::class, 'NOT_FOUND', 'Stream nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int) $stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }

            $rules = $stream->exclusions ?? [];

            return ToolResult::success([
                'stream_id'  => $stream->id,
                'exclusions' => array_values($rules),
                'count'      => count($rules),
                'team_id'    => $teamId,
                'message'    => empty($rules) ? 'Keine Ausschluss-Regeln definiert.' : null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Ausschlüsse: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'streams', 'exclusions'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
