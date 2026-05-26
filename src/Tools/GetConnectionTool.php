<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class GetConnectionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.connection.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/connections/{id} - Holt eine einzelne Connection. Credentials werden NIE zurückgegeben (nur die Auth-Feld-Keys, ohne Werte). ERFORDERLICH: connection_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Connection (ERFORDERLICH).',
                ],
            ],
            'required' => ['connection_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'connection_id', DatawarehouseConnection::class, 'NOT_FOUND', 'Connection nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseConnection $connection */
            $connection = $found['model'];

            if ((int)$connection->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Connection.');
            }

            $credentialKeys = is_array($connection->credentials) ? array_keys($connection->credentials) : [];

            return ToolResult::success([
                'id'                  => $connection->id,
                'uuid'                => $connection->uuid,
                'provider_key'        => $connection->provider_key,
                'name'                => $connection->name,
                'description'         => $connection->description,
                'meta'                => $connection->meta,
                'is_active'           => (bool)$connection->is_active,
                'last_check_at'       => $connection->last_check_at?->toISOString(),
                'last_check_status'   => $connection->last_check_status,
                'last_check_error'    => $connection->last_check_error,
                'credential_keys_set' => $credentialKeys,
                'team_id'             => $connection->team_id,
                'created_at'          => $connection->created_at?->toISOString(),
                'updated_at'          => $connection->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Connection: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'connections', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
