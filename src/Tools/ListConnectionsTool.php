<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ListConnectionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.connections.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/connections - Listet Datawarehouse-Connections (Zugänge zu externen Providern wie Lexoffice, Land, etc.). Credentials werden NIE zurückgegeben. Parameter: team_id (optional), provider_key (optional), is_active (optional), filters/search/sort/limit/offset.';
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
                    'provider_key' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Provider (z.B. "lexoffice"). Nutze "dwh.providers.GET" für die Liste.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive oder inaktive Connections.',
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

            $query = DatawarehouseConnection::query()
                ->withCount('streams')
                ->forTeam($teamId);

            if (isset($arguments['provider_key'])) {
                $query->where('provider_key', $arguments['provider_key']);
            }
            if (array_key_exists('is_active', $arguments)) {
                $query->where('is_active', (bool)$arguments['is_active']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'provider_key', 'is_active', 'last_check_status', 'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'description', 'provider_key']);
            $this->applyStandardSort($query, $arguments, [
                'name', 'provider_key', 'last_check_at', 'created_at', 'updated_at',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (DatawarehouseConnection $c) => [
                'id'                => $c->id,
                'uuid'              => $c->uuid,
                'provider_key'      => $c->provider_key,
                'name'              => $c->name,
                'description'       => $c->description,
                'meta'              => $c->meta,
                'is_active'         => (bool)$c->is_active,
                'last_check_at'     => $c->last_check_at?->toISOString(),
                'last_check_status' => $c->last_check_status,
                'last_check_error'  => $c->last_check_error,
                'streams_count'     => $c->streams_count,
                'team_id'           => $c->team_id,
                'created_at'        => $c->created_at?->toISOString(),
                'updated_at'        => $c->updated_at?->toISOString(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Connections: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'connections', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
