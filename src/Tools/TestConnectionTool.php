<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\ProviderRegistry;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.connections.test';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/connections/{id}/test - Führt einen leichtgewichtigen Verbindungstest mit den gespeicherten Credentials aus. ERFORDERLICH: connection_id. Setzt last_check_at, last_check_status und ggf. last_check_error auf der Connection.';
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

            $registry = app(ProviderRegistry::class);
            if (!$registry->has($connection->provider_key)) {
                $connection->markCheckError("Provider '{$connection->provider_key}' nicht registriert.");
                return ToolResult::error('VALIDATION_ERROR', "Provider '{$connection->provider_key}' ist nicht registriert.");
            }
            $provider = $registry->get($connection->provider_key);

            try {
                $ok = $provider->testConnection($connection);
                if ($ok) {
                    $connection->markCheckSuccess();
                    return ToolResult::success([
                        'id'                => $connection->id,
                        'provider_key'      => $connection->provider_key,
                        'last_check_at'     => $connection->last_check_at?->toISOString(),
                        'last_check_status' => $connection->last_check_status,
                        'message'           => 'Verbindungstest erfolgreich.',
                    ]);
                }
                $connection->markCheckError('Provider meldete keinen Erfolg.');
                return ToolResult::error('EXECUTION_ERROR', 'Verbindungstest fehlgeschlagen.');
            } catch (\Throwable $e) {
                $connection->markCheckError($e->getMessage());
                return ToolResult::error('EXECUTION_ERROR', 'Verbindungstest fehlgeschlagen: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Connection-Test: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'connections', 'test'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
