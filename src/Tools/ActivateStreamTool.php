<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\StreamSchemaService;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ActivateStreamTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.streams.activate';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/streams/{id}/activate - Aktiviert einen Stream aus dem Onboarding heraus. ERFORDERLICH: stream_id. Voraussetzungen: Stream im Status onboarding, mindestens eine Spalte definiert, bei sync_strategy=current/scd2 ein natural_key gesetzt. Erzeugt den dynamischen Stream-Table (sofern noch nicht vorhanden) und setzt den Status auf active.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'stream_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Streams (ERFORDERLICH).',
                ],
            ],
            'required' => ['stream_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'stream_id',
                DatawarehouseStream::class,
                'NOT_FOUND',
                'Stream nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int)$stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }

            if (!$stream->isOnboarding()) {
                return ToolResult::error('VALIDATION_ERROR', 'Stream ist nicht im Status "onboarding" (aktuell: '.$stream->status.').');
            }

            $columnCount = $stream->columns()->count();
            if ($columnCount === 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Stream hat keine Spalten. Lege zuerst Spalten über "dwh.stream_columns.BULK_POST" oder "dwh.stream_columns.POST" an.');
            }

            if (in_array($stream->sync_strategy, ['current', 'scd2'], true) && empty($stream->natural_key)) {
                return ToolResult::error('VALIDATION_ERROR', 'natural_key ist bei sync_strategy='.$stream->sync_strategy.' erforderlich.');
            }

            if (!$stream->table_created) {
                try {
                    app(StreamSchemaService::class)->createTable($stream, $context->user->id);
                    $stream->refresh();
                } catch (\Throwable $e) {
                    return ToolResult::error('EXECUTION_ERROR', 'Tabelle konnte nicht erstellt werden: '.$e->getMessage());
                }
            }

            $stream->update(['status' => 'active']);

            return ToolResult::success([
                'id'            => $stream->id,
                'uuid'          => $stream->uuid,
                'name'          => $stream->name,
                'status'        => $stream->status,
                'table_name'    => $stream->getDynamicTableName(),
                'table_created' => (bool)$stream->table_created,
                'team_id'       => $stream->team_id,
                'message'       => 'Stream aktiviert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktivieren des Streams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'activate'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
