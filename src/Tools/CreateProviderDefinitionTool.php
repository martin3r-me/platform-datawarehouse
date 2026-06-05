<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;
use Platform\Datawarehouse\Tools\Concerns\SerializesProviderDefinition;

class CreateProviderDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;
    use SerializesProviderDefinition;

    public function getName(): string
    {
        return 'datawarehouse.provider_definitions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/provider-definitions - Legt einen konfigurierbaren HTTP-Pull-Provider an (ohne Code). '
            . 'Der generierte "key" kann danach beim Anlegen einer Connection als provider_key genutzt werden. '
            . 'ERFORDERLICH: label, endpoints. Optional: base_url (leer → APP_URL), auth_type (none|bearer|header|query), '
            . 'auth_config ({header_name} bzw. {query_param}), description, icon, is_active. '
            . 'Tipp: nach dem Anlegen "datawarehouse.provider_definitions.test" nutzen, um einen Endpunkt zu prüfen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Anzeigename (ERFORDERLICH), z.B. "Helpdesk".',
                ],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung.'],
                'icon' => ['type' => 'string', 'description' => 'Optional: Heroicon-Name.'],
                'base_url' => [
                    'type' => 'string',
                    'description' => 'Basis-URL der API, z.B. "https://office.bhgdigital.de". Leer → config(app.url).',
                ],
                'auth_type' => [
                    'type' => 'string',
                    'enum' => ['none', 'bearer', 'header', 'query'],
                    'description' => 'Auth-Verfahren. bearer → Authorization: Bearer; header → benutzerdef. Header; query → URL-Param. Default: none.',
                ],
                'auth_config' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'Optional: {header_name} bei auth_type=header bzw. {query_param} bei auth_type=query.',
                ],
                'endpoints' => $this->endpointsSchema(),
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
            ],
            'required' => ['label', 'endpoints'],
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
            $teamId = (int) $resolved['team_id'];

            $label = trim((string) ($arguments['label'] ?? ''));
            if ($label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            }

            $authType = (string) ($arguments['auth_type'] ?? 'none');
            if (!in_array($authType, DatawarehouseProviderDefinition::AUTH_TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'auth_type muss none|bearer|header|query sein.');
            }

            $endpoints = $arguments['endpoints'] ?? null;
            if ($error = $this->validateEndpoints($endpoints)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }

            $authConfig = $arguments['auth_config'] ?? null;
            if ($authConfig !== null && !is_array($authConfig)) {
                return ToolResult::error('VALIDATION_ERROR', 'auth_config muss ein Objekt sein.');
            }

            $definition = DatawarehouseProviderDefinition::create([
                'team_id'     => $teamId,
                'user_id'     => $context->user->id,
                'label'       => $label,
                'description' => $arguments['description'] ?? null,
                'icon'        => $arguments['icon'] ?? null,
                'base_url'    => $arguments['base_url'] ?? null,
                'auth_type'   => $authType,
                'auth_config' => $authConfig,
                'endpoints'   => $endpoints,
                'is_active'   => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
            ]);

            return ToolResult::success([
                'provider_definition' => $this->serializeProviderDefinition($definition),
                'message' => 'Provider-Definition erstellt. Nutze den "key" als provider_key beim Anlegen einer Connection.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Provider-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'provider_definitions', 'providers', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
