<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class UpdateConnectionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.connections.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/connections/{id} - Aktualisiert eine Connection. ERFORDERLICH: connection_id. provider_key kann nicht geändert werden (eine andere Provider-Verbindung benötigt eine neue Connection). credentials werden — wenn angegeben — als Partial-Merge in die verschlüsselten Felder eingespielt (einzelne Felder können so überschrieben werden, ohne andere zu verlieren).';
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
                'name'        => ['type' => 'string', 'description' => 'Optional: neuer Name.'],
                'description' => ['type' => 'string', 'description' => 'Optional: neue Beschreibung.'],
                'credentials' => [
                    'type' => 'object',
                    'description' => 'Optional: Auth-Felder als Partial-Patch (nur übergebene Keys werden ersetzt).',
                    'additionalProperties' => true,
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Optional: Partial-Merge in die Metadaten.',
                    'additionalProperties' => true,
                ],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: aktiv/inaktiv.'],
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

            foreach (['name', 'description'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $connection->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }
            if (array_key_exists('is_active', $arguments)) {
                $connection->is_active = (bool)$arguments['is_active'];
            }

            if (array_key_exists('credentials', $arguments)) {
                $patch = $arguments['credentials'];
                if (!is_array($patch)) {
                    return ToolResult::error('VALIDATION_ERROR', 'credentials muss ein Objekt sein.');
                }
                $existing = is_array($connection->credentials) ? $connection->credentials : [];
                $connection->credentials = array_replace($existing, $patch);
            }

            if (array_key_exists('meta', $arguments)) {
                $patch = $arguments['meta'];
                if ($patch === null || $patch === []) {
                    $connection->meta = null;
                } elseif (is_array($patch)) {
                    $existing = is_array($connection->meta) ? $connection->meta : [];
                    $connection->meta = array_replace_recursive($existing, $patch);
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'meta muss ein Objekt sein.');
                }
            }

            $connection->save();

            return ToolResult::success([
                'id'           => $connection->id,
                'provider_key' => $connection->provider_key,
                'name'         => $connection->name,
                'is_active'    => (bool)$connection->is_active,
                'team_id'      => $connection->team_id,
                'message'      => 'Connection aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Connection: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'connections', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
