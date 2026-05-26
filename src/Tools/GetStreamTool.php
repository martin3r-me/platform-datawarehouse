<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class GetStreamTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.stream.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/streams/{id} - Holt einen einzelnen Stream inkl. Spalten-Definitionen und letztem Import-Status. ERFORDERLICH: stream_id. Nutze "dwh.streams.GET" um IDs zu finden.';
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
                    'description' => 'ID des Streams (ERFORDERLICH). Nutze "dwh.streams.GET" um IDs zu finden.',
                ],
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

            $stream->load('columns');
            $lastImport = $stream->imports()->latest()->first();

            return ToolResult::success([
                'id'               => $stream->id,
                'uuid'             => $stream->uuid,
                'name'             => $stream->name,
                'slug'             => $stream->slug,
                'description'      => $stream->description,
                'source_type'      => $stream->source_type,
                'connection_id'    => $stream->connection_id,
                'endpoint_key'     => $stream->endpoint_key,
                'pull_schedule'    => $stream->pull_schedule,
                'pull_mode'        => $stream->pull_mode,
                'incremental_field' => $stream->incremental_field,
                'last_cursor'      => $stream->last_cursor,
                'last_pull_at'     => $stream->last_pull_at?->toISOString(),
                'frequency'        => $stream->frequency,
                'mode'             => $stream->mode,
                'sync_strategy'    => $stream->sync_strategy,
                'natural_key'      => $stream->natural_key,
                'change_detection' => (bool)$stream->change_detection,
                'soft_delete'      => (bool)$stream->soft_delete,
                'upsert_key'       => $stream->upsert_key,
                'status'           => $stream->status,
                'is_system'        => (bool)$stream->is_system,
                'table_name'       => $stream->getDynamicTableName(),
                'table_created'    => (bool)$stream->table_created,
                'schema_version'   => $stream->schema_version,
                'pull_url'         => $stream->pull_url,
                'endpoint_token'   => $stream->endpoint_token,
                'columns'          => $stream->columns->map(fn ($c) => [
                    'id'           => $c->id,
                    'source_key'   => $c->source_key,
                    'column_name'  => $c->column_name,
                    'label'        => $c->label,
                    'data_type'    => $c->data_type,
                    'precision'    => $c->precision,
                    'scale'        => $c->scale,
                    'unit'         => $c->unit,
                    'is_indexed'   => (bool)$c->is_indexed,
                    'is_nullable'  => (bool)$c->is_nullable,
                    'default_value' => $c->default_value,
                    'transform'    => $c->transform,
                    'position'     => $c->position,
                    'is_active'    => (bool)$c->is_active,
                ])->values()->toArray(),
                'last_import'      => $lastImport ? [
                    'id'            => $lastImport->id,
                    'status'        => $lastImport->status,
                    'mode'          => $lastImport->mode,
                    'rows_received' => $lastImport->rows_received,
                    'rows_imported' => $lastImport->rows_imported,
                    'rows_skipped'  => $lastImport->rows_skipped,
                    'duration_ms'   => $lastImport->duration_ms,
                    'created_at'    => $lastImport->created_at?->toISOString(),
                ] : null,
                'last_run_at'      => $stream->last_run_at?->toISOString(),
                'last_status'      => $stream->last_status,
                'team_id'          => $stream->team_id,
                'created_at'       => $stream->created_at?->toISOString(),
                'updated_at'       => $stream->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Streams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'streams', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
