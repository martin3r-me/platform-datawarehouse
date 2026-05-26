<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Services\StreamSchemaService;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class DeleteStreamColumnTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_columns.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/stream-columns/{id} - Entfernt eine Spalten-Definition. ERFORDERLICH: column_id. Wenn der Stream-Table bereits angelegt ist, wird auch die DB-Spalte per ALTER TABLE entfernt — Daten in dieser Spalte gehen damit verloren.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'column_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Spalte (ERFORDERLICH).',
                ],
            ],
            'required' => ['column_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'column_id', DatawarehouseStreamColumn::class, 'NOT_FOUND', 'Spalte nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStreamColumn $column */
            $column = $found['model'];

            $stream = $column->stream;
            if (!$stream || (int)$stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Spalte.');
            }
            if ($stream->isSystem()) {
                return ToolResult::error('VALIDATION_ERROR', 'Spalten von System-Streams können nicht gelöscht werden.');
            }

            $alteredTable = false;
            if ($stream->table_created) {
                try {
                    app(StreamSchemaService::class)->dropColumn($stream, $column->column_name, [
                        'data_type'   => $column->data_type,
                        'is_nullable' => (bool)$column->is_nullable,
                    ], $context->user?->id);
                    $alteredTable = true;
                } catch (\Throwable $e) {
                    return ToolResult::error('EXECUTION_ERROR', 'ALTER TABLE DROP COLUMN fehlgeschlagen: ' . $e->getMessage());
                }
            }

            $column->delete();

            return ToolResult::success([
                'id'            => $column->id,
                'stream_id'     => $column->stream_id,
                'altered_table' => $alteredTable,
                'team_id'       => $teamId,
                'message'       => 'Spalte gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Spalte: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'stream_columns', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
