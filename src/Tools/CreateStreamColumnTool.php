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

class CreateStreamColumnTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_columns.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/stream-columns - Legt eine neue Spalten-Definition für einen Stream an. ERFORDERLICH: stream_id, source_key, data_type. Optional: column_name (default: aus source_key sanitiert), label, precision/scale (bei data_type=decimal), is_indexed, is_nullable, default_value, transform, position. Wenn der Stream bereits einen Table hat (table_created=true), wird die Spalte zusätzlich per ALTER TABLE hinzugefügt. Für Massen-Definition vor Aktivierung: "datawarehouse.stream_columns.BULK_POST".';
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
                'source_key' => [
                    'type' => 'string',
                    'description' => 'Pfad im Payload (ERFORDERLICH), z.B. "data.invoice.total".',
                ],
                'column_name' => [
                    'type' => 'string',
                    'description' => 'Optional: MySQL-Identifier der Spalte. Default: aus source_key abgeleitet (sanitiert).',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Optional: Anzeige-Label.',
                ],
                'data_type' => [
                    'type' => 'string',
                    'enum' => ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'],
                    'description' => 'Datentyp (ERFORDERLICH).',
                ],
                'precision' => [
                    'type' => 'integer',
                    'description' => 'Optional: Bei data_type=decimal. Default: 10.',
                ],
                'scale' => [
                    'type' => 'integer',
                    'description' => 'Optional: Bei data_type=decimal. Default: 2.',
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Optional: Anzeige-Einheit (z.B. "€", "kg").',
                ],
                'is_indexed' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Index anlegen. Default: false.',
                ],
                'is_nullable' => [
                    'type' => 'boolean',
                    'description' => 'Optional: NULL erlaubt. Default: true.',
                ],
                'default_value' => [
                    'type' => 'string',
                    'description' => 'Optional: Default-Wert.',
                ],
                'transform' => [
                    'type' => 'string',
                    'enum' => ['trim', 'url_decode', 'cast_german_decimal', 'lowercase', 'uppercase', 'strip_tags', 'to_integer', 'to_boolean', 'excel_serial_date', 'parse_german_date'],
                    'description' => 'Optional: Transformation beim Import.',
                ],
                'position' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierposition. Default: ans Ende.',
                ],
            ],
            'required' => ['stream_id', 'source_key', 'data_type'],
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

            $sourceKey = trim((string)($arguments['source_key'] ?? ''));
            if ($sourceKey === '') {
                return ToolResult::error('VALIDATION_ERROR', 'source_key ist erforderlich.');
            }

            $dataType = $arguments['data_type'] ?? '';
            $allowedTypes = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'];
            if (!in_array($dataType, $allowedTypes, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger data_type. Erlaubt: '.implode(', ', $allowedTypes).'.');
            }

            $columnName = isset($arguments['column_name']) && trim((string)$arguments['column_name']) !== ''
                ? StreamSchemaService::sanitizeColumnName((string)$arguments['column_name'])
                : StreamSchemaService::sanitizeColumnName(Str::snake($sourceKey));

            $existing = DatawarehouseStreamColumn::query()
                ->where('stream_id', $stream->id)
                ->where('column_name', $columnName)
                ->first();
            if ($existing) {
                return ToolResult::error('VALIDATION_ERROR', 'Spalte mit column_name "'.$columnName.'" existiert bereits.');
            }

            $position = isset($arguments['position'])
                ? (int)$arguments['position']
                : ((int)DatawarehouseStreamColumn::query()->where('stream_id', $stream->id)->max('position') + 1);

            $transform = $arguments['transform'] ?? null;
            $allowedTransforms = ['trim', 'url_decode', 'cast_german_decimal', 'lowercase', 'uppercase', 'strip_tags', 'to_integer', 'to_boolean', 'excel_serial_date', 'parse_german_date'];
            if ($transform !== null && !in_array($transform, $allowedTransforms, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger transform-Wert.');
            }

            $column = DatawarehouseStreamColumn::create([
                'stream_id'     => $stream->id,
                'source_key'    => $sourceKey,
                'column_name'   => $columnName,
                'label'         => $arguments['label'] ?? null,
                'data_type'     => $dataType,
                'precision'     => $arguments['precision'] ?? null,
                'scale'         => $arguments['scale'] ?? null,
                'unit'          => $arguments['unit'] ?? null,
                'is_indexed'    => (bool)($arguments['is_indexed'] ?? false),
                'is_nullable'   => array_key_exists('is_nullable', $arguments) ? (bool)$arguments['is_nullable'] : true,
                'default_value' => $arguments['default_value'] ?? null,
                'transform'     => $transform,
                'position'      => $position,
                'is_active'     => true,
            ]);

            $alteredTable = false;
            if ($stream->table_created) {
                try {
                    app(StreamSchemaService::class)->addColumn($stream, $column, $context->user->id);
                    $alteredTable = true;
                } catch (\Throwable $e) {
                    // Roll back the column entry to keep table and definitions in sync.
                    $column->delete();
                    return ToolResult::error('EXECUTION_ERROR', 'ALTER TABLE fehlgeschlagen: ' . $e->getMessage());
                }
            }

            return ToolResult::success([
                'id'            => $column->id,
                'stream_id'     => $column->stream_id,
                'source_key'    => $column->source_key,
                'column_name'   => $column->column_name,
                'data_type'     => $column->data_type,
                'position'      => $column->position,
                'altered_table' => $alteredTable,
                'team_id'       => $teamId,
                'message'       => $alteredTable
                    ? 'Spalte erstellt und Tabelle erweitert.'
                    : 'Spalte erstellt (Tabelle wird beim ersten Aktivieren angelegt).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Spalte: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'stream_columns', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
