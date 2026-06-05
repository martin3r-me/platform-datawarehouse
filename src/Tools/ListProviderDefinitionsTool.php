<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;
use Platform\Datawarehouse\Tools\Concerns\SerializesProviderDefinition;

class ListProviderDefinitionsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDwhTeam;
    use SerializesProviderDefinition;

    public function getName(): string
    {
        return 'datawarehouse.provider_definitions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/provider-definitions - Listet die konfigurierbaren HTTP-Pull-Provider des Teams '
            . '(per UI/LLM angelegt, im Gegensatz zu den Code-Providern aus "datawarehouse.providers.GET"). '
            . 'Optional: include_inactive.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'include_inactive' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Auch inaktive Definitionen einschließen. Default: false.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $query = DatawarehouseProviderDefinition::query()
                ->where('team_id', $teamId)
                ->orderBy('label');

            if (!($arguments['include_inactive'] ?? false)) {
                $query->where('is_active', true);
            }

            $definitions = $query->get()->map(fn ($d) => $this->serializeProviderDefinition($d))->all();

            return ToolResult::success([
                'data' => $definitions,
                'total' => count($definitions),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Provider-Definitionen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'provider_definitions', 'providers', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
