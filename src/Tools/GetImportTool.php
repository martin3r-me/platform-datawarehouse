<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class GetImportTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.import.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/imports/{id} - Holt einen einzelnen Import inkl. errors[]-Array (page, row, message). ERFORDERLICH: import_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'import_id' => ['type' => 'integer', 'description' => 'ID des Imports (ERFORDERLICH).'],
            ],
            'required' => ['import_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'import_id', DatawarehouseImport::class, 'NOT_FOUND', 'Import nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseImport $import */
            $import = $found['model'];

            $stream = $import->stream;
            if (!$stream || (int)$stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Import.');
            }

            return ToolResult::success([
                'id'            => $import->id,
                'stream_id'     => $import->stream_id,
                'stream_name'   => $stream->name,
                'status'        => $import->status,
                'mode'          => $import->mode,
                'rows_received' => $import->rows_received,
                'rows_imported' => $import->rows_imported,
                'rows_skipped'  => $import->rows_skipped,
                'duration_ms'   => $import->duration_ms,
                'errors'        => $import->error_log ?? [],
                'created_at'    => $import->created_at?->toISOString(),
                'updated_at'    => $import->updated_at?->toISOString(),
                'team_id'       => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Imports: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'imports', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
