<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class CreateStreamTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.streams.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/streams - Erstellt einen neuen Stream im Status "onboarding". ERFORDERLICH: name, source_type (webhook_post/pull_get/manual). Bei pull_get zusätzlich: connection_id, endpoint_key. Optional: description, slug, sync_strategy (append/current/snapshot/scd2), natural_key, pull_schedule, pull_mode, incremental_field. Nach Anlage: Spalten via "datawarehouse.stream_columns.BULK_POST" definieren, dann "datawarehouse.streams.activate" aufrufen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Streams (ERFORDERLICH).',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Optional: URL-/Table-tauglicher Slug. Default: aus name abgeleitet.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'enum' => ['webhook_post', 'pull_get', 'manual'],
                    'description' => 'Quellen-Typ (ERFORDERLICH). webhook_post = externer Sender, pull_get = zyklischer Provider-Pull, manual = manuelle Imports.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Bei source_type=pull_get ERFORDERLICH: Connection zum Provider. Nutze "datawarehouse.connections.GET".',
                ],
                'endpoint_key' => [
                    'type' => 'string',
                    'description' => 'Bei source_type=pull_get ERFORDERLICH: Endpoint-Key des Providers (z.B. "contacts" bei Lexoffice). Nutze "datawarehouse.provider.GET" um verfügbare Endpoints zu sehen.',
                ],
                'sync_strategy' => [
                    'type' => 'string',
                    'enum' => ['append', 'current', 'snapshot', 'scd2'],
                    'description' => 'Optional: Sync-Strategie. Default: append. current/scd2 erfordern natural_key.',
                ],
                'natural_key' => [
                    'type' => 'string',
                    'description' => 'Optional: Quell-Schlüssel zur Identifikation einer Entität. Bei sync_strategy=current oder scd2 ERFORDERLICH.',
                ],
                'pull_schedule' => [
                    'type' => 'string',
                    'description' => 'Optional: Schedule für pull_get-Streams (z.B. "hourly", "every_15_minutes", "daily", Cron-Expression).',
                ],
                'pull_mode' => [
                    'type' => 'string',
                    'enum' => ['full', 'incremental'],
                    'description' => 'Optional: Pull-Modus. Default: full. Bei incremental wird incremental_field benötigt.',
                ],
                'incremental_field' => [
                    'type' => 'string',
                    'description' => 'Optional: Feldname für inkrementelle Pulls (z.B. "updatedDate").',
                ],
                'change_detection' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Row-Hash-basiertes Change-Detection (überspringt unveränderte Zeilen). Default: false.',
                ],
                'soft_delete' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soft-Delete für nicht mehr im Pull enthaltene Rows. Nur bei vollen Pulls relevant. Default: false.',
                ],
                'pull_config' => [
                    'type' => 'object',
                    'description' => 'Optional: Provider-spezifische Konfiguration für den Pull (z.B. {"landkreis_id": "05111"} bei RKI, {"latitude": 51.23, "longitude": 6.78, "location_name": "Düsseldorf"} bei Open-Meteo).',
                    'additionalProperties' => true,
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freie Metadaten als JSON-Objekt.',
                    'additionalProperties' => true,
                ],
            ],
            'required' => ['name', 'source_type'],
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

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $sourceType = $arguments['source_type'] ?? null;
            if (!in_array($sourceType, ['webhook_post', 'pull_get', 'manual'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger source_type. Erlaubt: webhook_post, pull_get, manual.');
            }

            $connectionId = null;
            $endpointKey = null;
            if ($sourceType === 'pull_get') {
                $connectionId = (int)($arguments['connection_id'] ?? 0);
                $endpointKey = trim((string)($arguments['endpoint_key'] ?? ''));

                if ($connectionId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'connection_id ist bei source_type=pull_get erforderlich.');
                }
                if ($endpointKey === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'endpoint_key ist bei source_type=pull_get erforderlich.');
                }

                $connection = DatawarehouseConnection::query()
                    ->where('team_id', $teamId)
                    ->find($connectionId);
                if (!$connection) {
                    return ToolResult::error('NOT_FOUND', 'Connection nicht gefunden (oder kein Zugriff).');
                }
            }

            $syncStrategy = $arguments['sync_strategy'] ?? 'append';
            if (!in_array($syncStrategy, ['append', 'current', 'snapshot', 'scd2'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültige sync_strategy. Erlaubt: append, current, snapshot, scd2.');
            }

            $naturalKey = $arguments['natural_key'] ?? null;
            if (in_array($syncStrategy, ['current', 'scd2'], true) && empty($naturalKey)) {
                return ToolResult::error('VALIDATION_ERROR', 'natural_key ist bei sync_strategy='.$syncStrategy.' erforderlich.');
            }

            $pullMode = $arguments['pull_mode'] ?? null;
            if ($pullMode !== null && !in_array($pullMode, ['full', 'incremental'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger pull_mode. Erlaubt: full, incremental.');
            }
            if ($pullMode === 'incremental' && empty($arguments['incremental_field'])) {
                return ToolResult::error('VALIDATION_ERROR', 'incremental_field ist bei pull_mode=incremental erforderlich.');
            }

            $metadata = $arguments['metadata'] ?? null;
            if ($metadata !== null && !is_array($metadata)) {
                return ToolResult::error('VALIDATION_ERROR', 'metadata muss ein Objekt sein.');
            }

            $stream = DatawarehouseStream::create([
                'team_id'           => $teamId,
                'user_id'           => $context->user->id,
                'name'              => $name,
                'slug'              => $arguments['slug'] ?? null,
                'description'       => $arguments['description'] ?? null,
                'source_type'       => $sourceType,
                'connection_id'     => $connectionId,
                'endpoint_key'      => $endpointKey,
                'sync_strategy'     => $syncStrategy,
                'natural_key'       => $naturalKey,
                'pull_schedule'     => $arguments['pull_schedule'] ?? null,
                'pull_mode'         => $pullMode,
                'incremental_field' => $arguments['incremental_field'] ?? null,
                'pull_config'       => $arguments['pull_config'] ?? null,
                'change_detection'  => (bool)($arguments['change_detection'] ?? false),
                'soft_delete'       => (bool)($arguments['soft_delete'] ?? false),
                'status'            => 'onboarding',
                'is_system'         => false,
                'metadata'          => $metadata,
            ]);

            return ToolResult::success([
                'id'            => $stream->id,
                'uuid'          => $stream->uuid,
                'name'          => $stream->name,
                'slug'          => $stream->slug,
                'source_type'   => $stream->source_type,
                'sync_strategy' => $stream->sync_strategy,
                'status'        => $stream->status,
                'table_name'    => $stream->getDynamicTableName(),
                'team_id'       => $stream->team_id,
                'message'       => 'Stream im Status "onboarding" erstellt. Definiere jetzt die Spalten über "datawarehouse.stream_columns.BULK_POST" und aktiviere den Stream anschließend mit "datawarehouse.streams.activate".',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Streams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
