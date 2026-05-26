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

class CreateConnectionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.connections.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/connections - Legt eine neue Connection zu einem Provider an. ERFORDERLICH: provider_key (nutze "datawarehouse.providers.GET" für gültige Keys), name, credentials (Objekt mit allen Pflicht-Auth-Feldern des Providers — Schema via "datawarehouse.provider.GET"). Optional: description, meta, is_active. Credentials werden verschlüsselt gespeichert und in keiner Antwort zurückgegeben.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'provider_key' => [
                    'type' => 'string',
                    'description' => 'Provider-Key (ERFORDERLICH), z.B. "lexoffice". Liste via "datawarehouse.providers.GET".',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Anzeigename (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'credentials' => [
                    'type' => 'object',
                    'description' => 'ERFORDERLICH: Objekt mit Werten für alle Pflicht-Auth-Felder des Providers. Wird verschlüsselt gespeichert und nie zurückgegeben.',
                    'additionalProperties' => true,
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Optional: Freie Metadaten als JSON-Objekt.',
                    'additionalProperties' => true,
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Default true. Inaktive Connections werden von Pull-Streams ignoriert.',
                ],
            ],
            'required' => ['provider_key', 'name', 'credentials'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $providerKey = trim((string)($arguments['provider_key'] ?? ''));
            if ($providerKey === '') {
                return ToolResult::error('VALIDATION_ERROR', 'provider_key ist erforderlich.');
            }
            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            $credentials = $arguments['credentials'] ?? null;
            if (!is_array($credentials)) {
                return ToolResult::error('VALIDATION_ERROR', 'credentials muss ein Objekt sein.');
            }

            $registry = app(ProviderRegistry::class);
            if (!$registry->has($providerKey)) {
                return ToolResult::error('VALIDATION_ERROR', 'Unbekannter provider_key "'.$providerKey.'". Nutze "datawarehouse.providers.GET" für die Liste.');
            }

            $provider = $registry->get($providerKey);
            $missing = [];
            foreach ($provider->authFields() as $field) {
                if ($field->required && (!array_key_exists($field->key, $credentials) || $credentials[$field->key] === null || $credentials[$field->key] === '')) {
                    $missing[] = $field->key;
                }
            }
            if (!empty($missing)) {
                return ToolResult::error('VALIDATION_ERROR', 'Pflicht-Auth-Felder fehlen: '.implode(', ', $missing).'.');
            }

            $meta = $arguments['meta'] ?? null;
            if ($meta !== null && !is_array($meta)) {
                return ToolResult::error('VALIDATION_ERROR', 'meta muss ein Objekt sein.');
            }

            $connection = DatawarehouseConnection::create([
                'team_id'      => $teamId,
                'user_id'      => $context->user->id,
                'provider_key' => $providerKey,
                'name'         => $name,
                'description'  => $arguments['description'] ?? null,
                'credentials'  => $credentials,
                'meta'         => $meta,
                'is_active'    => array_key_exists('is_active', $arguments) ? (bool)$arguments['is_active'] : true,
            ]);

            return ToolResult::success([
                'id'           => $connection->id,
                'uuid'         => $connection->uuid,
                'provider_key' => $connection->provider_key,
                'name'         => $connection->name,
                'is_active'    => (bool)$connection->is_active,
                'team_id'      => $connection->team_id,
                'message'      => 'Connection erstellt. Nutze "datawarehouse.connections.test" um die Credentials zu prüfen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Connection: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'connections', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
