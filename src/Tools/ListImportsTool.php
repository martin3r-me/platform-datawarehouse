<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ListImportsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.imports.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/imports - Listet Import-Läufe (read-only) für einen Stream oder das ganze Team. Optional: stream_id, status (pending/processing/success/partial/error), filters/search/sort/limit/offset. Default-Sortierung: neueste zuerst. Achtung: rohes payload-Feld wird aus Bandbreitengründen NICHT mit zurückgegeben (Einzelimport via "datawarehouse.import.GET").';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                    'stream_id' => ['type' => 'integer', 'description' => 'Optional: Nur Imports eines bestimmten Streams.'],
                    'status'    => [
                        'type' => 'string',
                        'enum' => ['pending', 'processing', 'success', 'partial', 'error'],
                        'description' => 'Optional: Filter nach Status.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $query = DatawarehouseImport::query()
                ->whereIn('stream_id', DatawarehouseStream::query()->where('team_id', $teamId)->pluck('id'));

            if (isset($arguments['stream_id'])) {
                $streamId = (int)$arguments['stream_id'];
                // Auch Team-Check via Subquery oben — zusätzlich enge Eingrenzung.
                $query->where('stream_id', $streamId);
            }
            if (isset($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'status', 'mode', 'stream_id', 'created_at',
            ]);
            $this->applyStandardSort($query, $arguments, [
                'created_at', 'duration_ms', 'rows_received', 'rows_imported',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (DatawarehouseImport $i) => [
                'id'            => $i->id,
                'stream_id'     => $i->stream_id,
                'status'        => $i->status,
                'mode'          => $i->mode,
                'rows_received' => $i->rows_received,
                'rows_imported' => $i->rows_imported,
                'rows_skipped'  => $i->rows_skipped,
                'duration_ms'   => $i->duration_ms,
                'error_count'   => is_array($i->error_log) ? count($i->error_log) : 0,
                'created_at'    => $i->created_at?->toISOString(),
                'updated_at'    => $i->updated_at?->toISOString(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Imports: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'imports', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
