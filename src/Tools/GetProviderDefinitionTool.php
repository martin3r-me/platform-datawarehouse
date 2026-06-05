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

class GetProviderDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;
    use SerializesProviderDefinition;

    public function getName(): string
    {
        return 'datawarehouse.provider_definition.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/provider-definitions/{id} - Holt eine konfigurierbare Provider-Definition inkl. '
            . 'aller Endpunkte und Auth-Konfiguration. ERFORDERLICH: provider_definition_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'provider_definition_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Provider-Definition (ERFORDERLICH).',
                ],
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

            return ToolResult::success($this->serializeProviderDefinition($definition));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Provider-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'provider_definitions', 'providers', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
