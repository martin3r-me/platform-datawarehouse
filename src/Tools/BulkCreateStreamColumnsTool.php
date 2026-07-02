<?php

namespace Platform\Datawarehouse\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Services\StreamSchemaService;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class BulkCreateStreamColumnsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_columns.BULK_POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/stream-columns/bulk - Erstellt mehrere Spalten-Definitionen für einen Stream in einem Aufruf. Gedacht für den Onboarding-Flow nach "datawarehouse.streams.POST". ERFORDERLICH: stream_id, items (Array). Jedes Item benötigt source_key + data_type. Maximal 50 Items pro Aufruf. Wenn der Stream bereits einen Table hat (table_created=true), wird jede neue Spalte zusätzlich per ALTER TABLE hinzugefügt — fehlerhafte Items werden im "errors"-Array zurückgegeben, erfolgreiche bleiben bestehen.';
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
                    'description' => 'ID des Streams (ERFORDERLICH).',
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Spalten-Definitionen. Maximal 50 Items.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'source_key'    => ['type' => 'string', 'description' => 'Pfad im Payload (ERFORDERLICH).'],
                            'column_name'   => ['type' => 'string', 'description' => 'Optional: MySQL-Identifier. Default: aus source_key abgeleitet.'],
                            'label'         => ['type' => 'string', 'description' => 'Optional: Label.'],
                            'data_type'     => [
                                'type' => 'string',
                                'enum' => ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'],
                                'description' => 'Datentyp (ERFORDERLICH).',
                            ],
                            'precision'     => ['type' => 'integer'],
                            'scale'         => ['type' => 'integer'],
                            'unit'          => ['type' => 'string'],
                            'is_indexed'    => ['type' => 'boolean'],
                            'is_nullable'   => ['type' => 'boolean'],
                            'default_value' => ['type' => 'string'],
                            'transform'     => [
                                'type' => 'string',
                                'enum' => ['trim', 'url_decode', 'cast_german_decimal', 'lowercase', 'uppercase', 'strip_tags', 'to_integer', 'to_boolean', 'excel_serial_date', 'parse_german_date'],
                            ],
                            'position'      => ['type' => 'integer'],
                        ],
                        'required' => ['source_key', 'data_type'],
                    ],
                ],
            ],
            'required' => ['stream_id', 'items'],
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

            $streamId = (int)($arguments['stream_id'] ?? 0);
            if ($streamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'stream_id ist erforderlich.');
            }

            $stream = DatawarehouseStream::query()->where('team_id', $teamId)->find($streamId);
            if (!$stream) {
                return ToolResult::error('NOT_FOUND', 'Stream nicht gefunden (oder kein Zugriff).');
            }
            if ($stream->isSystem()) {
                return ToolResult::error('VALIDATION_ERROR', 'Spalten von System-Streams können nicht geändert werden.');
            }

            $items = $arguments['items'] ?? [];
            if (empty($items) || !is_array($items)) {
                return ToolResult::error('VALIDATION_ERROR', 'items ist erforderlich und muss ein nicht-leeres Array sein.');
            }
            if (count($items) > 50) {
                return ToolResult::error('VALIDATION_ERROR', 'Maximal 50 Items pro Bulk-Aufruf erlaubt.');
            }

            $allowedTypes = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'];
            $allowedTransforms = ['trim', 'url_decode', 'cast_german_decimal', 'lowercase', 'uppercase', 'strip_tags', 'to_integer', 'to_boolean', 'excel_serial_date', 'parse_german_date'];

            $existingNames = DatawarehouseStreamColumn::query()
                ->where('stream_id', $stream->id)
                ->pluck('column_name')
                ->all();

            $maxPosition = (int)DatawarehouseStreamColumn::query()
                ->where('stream_id', $stream->id)
                ->max('position');

            $created = [];
            $errors = [];
            $schemaService = $stream->table_created ? app(StreamSchemaService::class) : null;

            foreach ($items as $index => $item) {
                $sourceKey = trim((string)($item['source_key'] ?? ''));
                if ($sourceKey === '') {
                    $errors[] = ['index' => $index, 'error' => 'source_key ist erforderlich.'];
                    continue;
                }
                $dataType = $item['data_type'] ?? '';
                if (!in_array($dataType, $allowedTypes, true)) {
                    $errors[] = ['index' => $index, 'source_key' => $sourceKey, 'error' => 'Ungültiger data_type.'];
                    continue;
                }
                $transform = $item['transform'] ?? null;
                if ($transform !== null && !in_array($transform, $allowedTransforms, true)) {
                    $errors[] = ['index' => $index, 'source_key' => $sourceKey, 'error' => 'Ungültiger transform-Wert.'];
                    continue;
                }

                $columnName = isset($item['column_name']) && trim((string)$item['column_name']) !== ''
                    ? StreamSchemaService::sanitizeColumnName((string)$item['column_name'])
                    : StreamSchemaService::sanitizeColumnName(Str::snake($sourceKey));

                if (in_array($columnName, $existingNames, true)) {
                    $errors[] = ['index' => $index, 'source_key' => $sourceKey, 'column_name' => $columnName, 'error' => 'column_name existiert bereits.'];
                    continue;
                }

                try {
                    $position = isset($item['position']) ? (int)$item['position'] : (++$maxPosition);

                    $column = DatawarehouseStreamColumn::create([
                        'stream_id'     => $stream->id,
                        'source_key'    => $sourceKey,
                        'column_name'   => $columnName,
                        'label'         => $item['label'] ?? null,
                        'data_type'     => $dataType,
                        'precision'     => $item['precision'] ?? null,
                        'scale'         => $item['scale'] ?? null,
                        'unit'          => $item['unit'] ?? null,
                        'is_indexed'    => (bool)($item['is_indexed'] ?? false),
                        'is_nullable'   => array_key_exists('is_nullable', $item) ? (bool)$item['is_nullable'] : true,
                        'default_value' => $item['default_value'] ?? null,
                        'transform'     => $transform,
                        'position'      => $position,
                        'is_active'     => true,
                    ]);

                    $alteredTable = false;
                    if ($schemaService) {
                        try {
                            $schemaService->addColumn($stream, $column, $context->user->id);
                            $alteredTable = true;
                        } catch (\Throwable $e) {
                            $column->delete();
                            $errors[] = ['index' => $index, 'source_key' => $sourceKey, 'column_name' => $columnName, 'error' => 'ALTER TABLE: ' . $e->getMessage()];
                            continue;
                        }
                    }

                    $existingNames[] = $columnName;

                    $created[] = [
                        'index'         => $index,
                        'id'            => $column->id,
                        'source_key'    => $column->source_key,
                        'column_name'   => $column->column_name,
                        'data_type'     => $column->data_type,
                        'position'      => $column->position,
                        'altered_table' => $alteredTable,
                    ];
                } catch (\Throwable $e) {
                    $errors[] = ['index' => $index, 'source_key' => $sourceKey, 'error' => $e->getMessage()];
                }
            }

            return ToolResult::success([
                'stream_id'     => $stream->id,
                'created_count' => count($created),
                'error_count'   => count($errors),
                'created'       => $created,
                'errors'        => $errors,
                'team_id'       => $teamId,
                'message'       => count($created) . ' Spalte(n) erstellt, ' . count($errors) . ' Fehler.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Erstellen der Spalten: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'stream_columns', 'bulk', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
