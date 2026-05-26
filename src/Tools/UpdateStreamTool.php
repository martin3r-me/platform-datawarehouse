<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class UpdateStreamTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.streams.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/streams/{id} - Aktualisiert einen Stream. ERFORDERLICH: stream_id. Für Statuswechsel (onboarding→active, pause/resume/archive) gibt es eigene Tools (datawarehouse.streams.activate/pause/resume/archive). System-Streams (is_system=true) können nicht geändert werden. sync_strategy kann nur geändert werden solange table_created=false ist.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'stream_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Streams (ERFORDERLICH). Nutze "datawarehouse.streams.GET".',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
                ],
                'pull_schedule' => [
                    'type' => 'string',
                    'description' => 'Optional: Schedule für pull_get-Streams.',
                ],
                'pull_mode' => [
                    'type' => 'string',
                    'enum' => ['full', 'incremental'],
                    'description' => 'Optional: full oder incremental.',
                ],
                'incremental_field' => [
                    'type' => 'string',
                    'description' => 'Optional: Feldname für inkrementelle Pulls.',
                ],
                'sync_strategy' => [
                    'type' => 'string',
                    'enum' => ['append', 'current', 'snapshot', 'scd2'],
                    'description' => 'Optional: Sync-Strategie. Nur änderbar solange table_created=false.',
                ],
                'natural_key' => [
                    'type' => 'string',
                    'description' => 'Optional: Quell-Schlüssel zur Identifikation einer Entität.',
                ],
                'change_detection' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Row-Hash-basierte Change-Detection an/aus.',
                ],
                'soft_delete' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soft-Delete für nicht mehr im Pull enthaltene Rows an/aus.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freie Metadaten (Partial-Merge, kein vollständiger Ersatz).',
                    'additionalProperties' => true,
                ],
            ],
            'required' => ['stream_id'],
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

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'stream_id',
                DatawarehouseStream::class,
                'NOT_FOUND',
                'Stream nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int)$stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }

            if ($stream->isSystem()) {
                return ToolResult::error('VALIDATION_ERROR', 'System-Streams können nicht über die API geändert werden.');
            }

            foreach (['name', 'description', 'pull_schedule', 'incremental_field', 'natural_key'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $stream->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            if (array_key_exists('pull_mode', $arguments)) {
                $pullMode = $arguments['pull_mode'];
                if ($pullMode !== null && !in_array($pullMode, ['full', 'incremental'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger pull_mode. Erlaubt: full, incremental.');
                }
                $stream->pull_mode = $pullMode;
            }

            if (array_key_exists('sync_strategy', $arguments)) {
                if ($stream->table_created) {
                    return ToolResult::error('VALIDATION_ERROR', 'sync_strategy kann nicht mehr geändert werden, da der Stream-Table bereits erzeugt wurde.');
                }
                $sync = $arguments['sync_strategy'];
                if (!in_array($sync, ['append', 'current', 'snapshot', 'scd2'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültige sync_strategy.');
                }
                $stream->sync_strategy = $sync;
            }

            if (array_key_exists('change_detection', $arguments)) {
                $stream->change_detection = (bool)$arguments['change_detection'];
            }
            if (array_key_exists('soft_delete', $arguments)) {
                $stream->soft_delete = (bool)$arguments['soft_delete'];
            }

            if (array_key_exists('metadata', $arguments)) {
                $patch = $arguments['metadata'];
                if ($patch === null || $patch === []) {
                    $stream->metadata = null;
                } elseif (is_array($patch)) {
                    $existing = is_array($stream->metadata) ? $stream->metadata : [];
                    $stream->metadata = array_replace_recursive($existing, $patch);
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'metadata muss ein Objekt sein.');
                }
            }

            // natural_key bei strategie-pflicht
            if (in_array($stream->sync_strategy, ['current', 'scd2'], true) && empty($stream->natural_key)) {
                return ToolResult::error('VALIDATION_ERROR', 'natural_key ist bei sync_strategy='.$stream->sync_strategy.' erforderlich.');
            }

            $stream->save();

            return ToolResult::success([
                'id'            => $stream->id,
                'uuid'          => $stream->uuid,
                'name'          => $stream->name,
                'slug'          => $stream->slug,
                'source_type'   => $stream->source_type,
                'sync_strategy' => $stream->sync_strategy,
                'pull_mode'     => $stream->pull_mode,
                'pull_schedule' => $stream->pull_schedule,
                'status'        => $stream->status,
                'team_id'       => $stream->team_id,
                'message'       => 'Stream aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Streams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
