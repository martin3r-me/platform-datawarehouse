<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseRkvConfig;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Returns the team's RKV Rückvergütung (JRV) configuration — the staffeln
 * (Event Rent + eventura), eventura WKZ steps, growth factor, prior-year
 * reference and stream/column mapping. Seeds tracker defaults on first access.
 * Read-only.
 */
class GetRkvConfigTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.rkv_config.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/rkv/config - Liefert die RKV-Rückvergütungs-Konfiguration des Teams: Staffeln (Event Rent + eventura), eventura-WKZ-Stufen, Wachstumsfaktor, Vorjahr (2025) je Monat und Stream/Spalten-Mapping. Legt beim ersten Aufruf die Tracker-Defaults an. Diese Parameter steuern die JRV-Hochrechnung (Seite "RKV Rückvergütung"). Änderbar via "datawarehouse.rkv_config.PUT".';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
            ],
            'required' => [],
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

            $config = DatawarehouseRkvConfig::forTeamOrDefault($teamId, $context->user?->id);

            return ToolResult::success([
                'team_id' => $teamId,
                'config'  => $config->config,
                'message' => 'RKV-Konfiguration. Änderbar via "datawarehouse.rkv_config.PUT" (partielles Update einzelner Abschnitte).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der RKV-Konfiguration: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'rkv', 'config', 'staffeln'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
