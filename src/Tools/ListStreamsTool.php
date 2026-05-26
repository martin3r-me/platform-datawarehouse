<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ListStreamsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.streams.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/streams - Listet Datawarehouse-Streams. Parameter: team_id (optional), status (optional, eines von onboarding/active/paused/archived), source_type (optional, eines von webhook_post/pull_get/manual), include_system (optional, default false), filters/search/sort/limit/offset (optional).';
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
                    'status' => [
                        'type' => 'string',
                        'enum' => ['onboarding', 'active', 'paused', 'archived'],
                        'description' => 'Optional: Filter nach Status.',
                    ],
                    'source_type' => [
                        'type' => 'string',
                        'enum' => ['webhook_post', 'pull_get', 'manual'],
                        'description' => 'Optional: Filter nach Stream-Typ.',
                    ],
                    'include_system' => [
                        'type' => 'boolean',
                        'description' => 'Optional: System-Streams (z.B. Land, Sprache, Feiertage) einschließen. Default: false.',
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

            $query = DatawarehouseStream::query()
                ->withCount('columns', 'imports')
                ->forTeam($teamId);

            if (empty($arguments['include_system'])) {
                $query->userCreated();
            }

            if (isset($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (isset($arguments['source_type'])) {
                $query->where('source_type', $arguments['source_type']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'slug', 'status', 'source_type', 'sync_strategy', 'connection_id', 'endpoint_key', 'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'slug', 'description']);
            $this->applyStandardSort($query, $arguments, [
                'name', 'status', 'source_type', 'last_run_at', 'created_at', 'updated_at',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (DatawarehouseStream $s) {
                return [
                    'id'               => $s->id,
                    'uuid'             => $s->uuid,
                    'name'             => $s->name,
                    'slug'             => $s->slug,
                    'description'      => $s->description,
                    'source_type'      => $s->source_type,
                    'sync_strategy'    => $s->sync_strategy,
                    'natural_key'      => $s->natural_key,
                    'status'           => $s->status,
                    'is_system'        => (bool)$s->is_system,
                    'connection_id'    => $s->connection_id,
                    'endpoint_key'     => $s->endpoint_key,
                    'pull_schedule'    => $s->pull_schedule,
                    'pull_mode'        => $s->pull_mode,
                    'table_name'       => $s->getDynamicTableName(),
                    'table_created'    => (bool)$s->table_created,
                    'columns_count'    => $s->columns_count,
                    'imports_count'    => $s->imports_count,
                    'last_run_at'      => $s->last_run_at?->toISOString(),
                    'last_status'      => $s->last_status,
                    'team_id'          => $s->team_id,
                    'created_at'       => $s->created_at?->toISOString(),
                    'updated_at'       => $s->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Streams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'streams', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
