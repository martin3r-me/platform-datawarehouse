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

class UpdateProviderDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;
    use SerializesProviderDefinition;

    public function getName(): string
    {
        return 'datawarehouse.provider_definitions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/provider-definitions/{id} - Aktualisiert eine konfigurierbare Provider-Definition. '
            . 'Nur übergebene Felder werden geändert. endpoints überschreibt die komplette Endpunkt-Liste. '
            . 'ERFORDERLICH: provider_definition_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'provider_definition_id' => ['type' => 'integer', 'description' => 'ID der Provider-Definition (ERFORDERLICH).'],
                'label' => ['type' => 'string', 'description' => 'Optional: Anzeigename.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung.'],
                'icon' => ['type' => 'string', 'description' => 'Optional: Heroicon-Name.'],
                'base_url' => ['type' => 'string', 'description' => 'Optional: Basis-URL.'],
                'auth_type' => [
                    'type' => 'string',
                    'enum' => ['none', 'bearer', 'header', 'query'],
                    'description' => 'Optional: Auth-Verfahren.',
                ],
                'auth_config' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Optional: {header_name} bzw. {query_param}.'],
                'endpoints' => $this->endpointsSchema(),
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: aktiv/inaktiv.'],
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

            $update = [];
            foreach (['label', 'description', 'icon', 'base_url'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = $arguments[$field];
                }
            }

            if (array_key_exists('auth_type', $arguments)) {
                if (!in_array($arguments['auth_type'], DatawarehouseProviderDefinition::AUTH_TYPES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'auth_type muss none|bearer|header|query sein.');
                }
                $update['auth_type'] = $arguments['auth_type'];
            }

            if (array_key_exists('auth_config', $arguments)) {
                if ($arguments['auth_config'] !== null && !is_array($arguments['auth_config'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'auth_config muss ein Objekt sein.');
                }
                $update['auth_config'] = $arguments['auth_config'];
            }

            if (array_key_exists('endpoints', $arguments)) {
                if ($error = $this->validateEndpoints($arguments['endpoints'])) {
                    return ToolResult::error('VALIDATION_ERROR', $error);
                }
                $update['endpoints'] = $arguments['endpoints'];
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (!empty($update)) {
                $definition->update($update);
            }

            return ToolResult::success([
                'provider_definition' => $this->serializeProviderDefinition($definition->fresh()),
                'message' => 'Provider-Definition aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Provider-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'provider_definitions', 'providers', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
