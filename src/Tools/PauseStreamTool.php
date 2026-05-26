<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class PauseStreamTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.streams.pause';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/streams/{id}/pause - Setzt einen aktiven Stream auf "paused" — Pull-Schedules feuern dann nicht mehr, Webhook-Ingest wird abgelehnt. ERFORDERLICH: stream_id. Nutze "dwh.streams.resume" um wieder zu aktivieren.';
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
            $teamId = (int)$resolved['team_id'];

            $found = $this->validateAndFindModel($arguments, $context, 'stream_id', DatawarehouseStream::class, 'NOT_FOUND', 'Stream nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int)$stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }
            if ($stream->isSystem()) {
                return ToolResult::error('VALIDATION_ERROR', 'System-Streams können nicht pausiert werden.');
            }
            if (!$stream->isActive()) {
                return ToolResult::error('VALIDATION_ERROR', 'Nur aktive Streams können pausiert werden (aktuell: '.$stream->status.').');
            }

            $stream->update(['status' => 'paused']);

            return ToolResult::success([
                'id' => $stream->id,
                'status' => $stream->status,
                'team_id' => $stream->team_id,
                'message' => 'Stream pausiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Pausieren des Streams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'pause'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
