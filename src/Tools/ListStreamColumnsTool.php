<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ListStreamColumnsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_columns.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/stream-columns - Listet Spalten-Definitionen eines Streams. ERFORDERLICH: stream_id. Optional: is_active-Filter, filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'stream_id' => [
                        'type' => 'integer',
                        'description' => 'ID des Streams (ERFORDERLICH). Nutze "datawarehouse.streams.GET".',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive (true) oder inaktive (false) Spalten.',
                    ],
                ],
                'required' => ['stream_id'],
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

            $streamId = (int)($arguments['stream_id'] ?? 0);
            if ($streamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'stream_id ist erforderlich.');
            }

            $stream = DatawarehouseStream::query()->where('team_id', $teamId)->find($streamId);
            if (!$stream) {
                return ToolResult::error('NOT_FOUND', 'Stream nicht gefunden (oder kein Zugriff).');
            }

            $query = DatawarehouseStreamColumn::query()->where('stream_id', $stream->id);

            if (array_key_exists('is_active', $arguments)) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'column_name', 'source_key', 'data_type', 'is_active', 'is_indexed', 'is_nullable', 'position',
            ]);
            $this->applyStandardSearch($query, $arguments, ['column_name', 'source_key', 'label']);
            $this->applyStandardSort($query, $arguments, [
                'position', 'column_name', 'data_type', 'created_at',
            ], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (DatawarehouseStreamColumn $c) => [
                'id'            => $c->id,
                'stream_id'     => $c->stream_id,
                'source_key'    => $c->source_key,
                'column_name'   => $c->column_name,
                'label'         => $c->label,
                'data_type'     => $c->data_type,
                'precision'     => $c->precision,
                'scale'         => $c->scale,
                'unit'          => $c->unit,
                'is_indexed'    => (bool)$c->is_indexed,
                'is_nullable'   => (bool)$c->is_nullable,
                'default_value' => $c->default_value,
                'transform'     => $c->transform,
                'position'      => $c->position,
                'is_active'     => (bool)$c->is_active,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'stream_id' => $stream->id,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Spalten: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'stream_columns', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
