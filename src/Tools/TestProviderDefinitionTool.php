<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Providers\Generic\GenericHttpProvider;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class TestProviderDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.provider_definitions.test';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/provider-definitions/{id}/test - Holt eine Beispielseite eines Endpunkts und gibt '
            . 'Sample-Zeilen + erkannte Felder zurück (ideal, um data_path/Pagination/Auth zu prüfen, bevor ein Stream '
            . 'angelegt wird). ERFORDERLICH: provider_definition_id. Optional: endpoint_key (Default: erster Endpunkt), '
            . 'connection_id (für Credentials einer bestehenden Connection) ODER credentials ({token} bzw. {api_key}), max_rows.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'provider_definition_id' => ['type' => 'integer', 'description' => 'ID der Provider-Definition (ERFORDERLICH).'],
                'endpoint_key' => ['type' => 'string', 'description' => 'Optional: Endpunkt-Key. Default: erster Endpunkt.'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: bestehende Connection für Credentials.'],
                'credentials' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'Optional: Inline-Credentials, z.B. {"token":"..."} (bearer) oder {"api_key":"..."} (header/query). Nicht persistiert.',
                ],
                'max_rows' => ['type' => 'integer', 'description' => 'Optional: Max. Sample-Zeilen in der Antwort. Default: 3.'],
            ],
            'required' => ['provider_definition_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $found = $this->validateAndFindModel($arguments, $context, 'provider_definition_id', DatawarehouseProviderDefinition::class, 'NOT_FOUND', 'Provider-Definition nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseProviderDefinition $definition */
            $definition = $found['model'];

            if ((int) $definition->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Provider-Definition.');
            }

            $provider = new GenericHttpProvider($definition);
            $endpoints = $provider->endpoints();
            if (empty($endpoints)) {
                return ToolResult::error('VALIDATION_ERROR', 'Die Provider-Definition hat keine Endpunkte.');
            }

            $endpointKey = $arguments['endpoint_key'] ?? array_key_first($endpoints);
            if (!isset($endpoints[$endpointKey])) {
                return ToolResult::error('VALIDATION_ERROR', 'Unbekannter endpoint_key. Verfügbar: ' . implode(', ', array_keys($endpoints)) . '.');
            }

            // Resolve credentials: an existing connection wins; otherwise build a transient one.
            $connection = $this->resolveConnection($arguments, $teamId, $definition->key);
            if ($connection instanceof ToolResult) {
                return $connection;
            }

            $pullContext = new PullContext(
                connection:  $connection,
                stream:      new DatawarehouseStream(),
                endpoint:    $endpoints[$endpointKey],
                cursor:      null,
                incremental: false,
            );

            $result = $provider->fetch($pullContext);

            $maxRows = max(1, (int) ($arguments['max_rows'] ?? 3));
            $sample = array_slice($result->rows, 0, $maxRows);
            $fields = !empty($result->rows) && is_array($result->rows[0]) ? array_keys($result->rows[0]) : [];

            return ToolResult::success([
                'endpoint_key'    => $endpointKey,
                'received_count'  => $result->count(),
                'detected_fields' => $fields,
                'has_more_pages'  => !$result->isLastPage(),
                'sample_rows'     => $sample,
                'message'         => 'Test erfolgreich. Nutze detected_fields als source_key für Stream-Spalten.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Test fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * @return DatawarehouseConnection|ToolResult
     */
    protected function resolveConnection(array $arguments, int $teamId, string $providerKey)
    {
        if (!empty($arguments['connection_id'])) {
            $connection = DatawarehouseConnection::find((int) $arguments['connection_id']);
            if (!$connection || (int) $connection->team_id !== $teamId) {
                return ToolResult::error('NOT_FOUND', 'Connection nicht gefunden oder kein Zugriff.');
            }
            return $connection;
        }

        // Transient connection (not persisted) carrying inline credentials for the test.
        $connection = new DatawarehouseConnection([
            'team_id'      => $teamId,
            'provider_key' => $providerKey,
            'name'         => 'test',
        ]);
        $credentials = $arguments['credentials'] ?? [];
        if (!is_array($credentials)) {
            return ToolResult::error('VALIDATION_ERROR', 'credentials muss ein Objekt sein.');
        }
        $connection->credentials = $credentials;

        return $connection;
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'provider_definitions', 'providers', 'test'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
