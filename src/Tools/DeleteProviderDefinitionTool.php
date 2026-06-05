<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class DeleteProviderDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.provider_definitions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/provider-definitions/{id} - Soft-deletet eine konfigurierbare Provider-Definition. '
            . 'Connections, die ihren key nutzen, schlagen anschließend beim Pull fehl. ERFORDERLICH: provider_definition_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'provider_definition_id' => ['type' => 'integer', 'description' => 'ID der Provider-Definition (ERFORDERLICH).'],
                'force' => ['type' => 'boolean', 'description' => 'Optional: Auch löschen, wenn Connections diesen Provider nutzen. Default: false.'],
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

            $force = (bool) ($arguments['force'] ?? false);
            $usedBy = DatawarehouseConnection::query()
                ->where('team_id', $teamId)
                ->where('provider_key', $definition->key)
                ->count();
            if ($usedBy > 0 && !$force) {
                return ToolResult::error('VALIDATION_ERROR', 'Provider wird noch von ' . $usedBy . ' Connection(s) genutzt. Connections zuerst entfernen oder force=true setzen.');
            }

            $definition->delete();

            return ToolResult::success([
                'id'      => $definition->id,
                'key'     => $definition->key,
                'team_id' => $definition->team_id,
                'message' => 'Provider-Definition gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Provider-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'provider_definitions', 'providers', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
