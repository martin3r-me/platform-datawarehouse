<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class DeleteConnectionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.connections.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/connections/{id} - Soft-deletet eine Connection. Streams, die diese Connection nutzen, schlagen anschließend beim Pull fehl — vorher den Stream-Referenzcheck via "datawarehouse.connection.GET" (streams_count) durchführen.';
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
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Auch löschen, wenn aktive Streams (status=active oder onboarding) diese Connection nutzen. Default: false.',
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

            $force = (bool)($arguments['force'] ?? false);
            $activeStreams = $connection->streams()->whereIn('status', ['onboarding', 'active', 'paused'])->count();
            if ($activeStreams > 0 && !$force) {
                return ToolResult::error('VALIDATION_ERROR', 'Connection wird noch von '.$activeStreams.' Stream(s) genutzt. Streams zuerst archivieren oder force=true setzen.');
            }

            $connection->delete();

            return ToolResult::success([
                'id'      => $connection->id,
                'team_id' => $connection->team_id,
                'message' => 'Connection gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Connection: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'connections', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
