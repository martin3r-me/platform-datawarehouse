<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Services\StreamSchemaService;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class UpdateStreamColumnTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.stream_columns.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/stream-columns/{id} - Aktualisiert eine Spalten-Definition. ERFORDERLICH: column_id. column_name kann nicht geändert werden. Bei Änderung an data_type/is_nullable und bereits erzeugtem Table wird ALTER TABLE ausgeführt.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'column_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Spalte (ERFORDERLICH).',
                ],
                'source_key'    => ['type' => 'string', 'description' => 'Optional: neuer Pfad im Payload.'],
                'label'         => ['type' => 'string', 'description' => 'Optional: neues Label.'],
                'data_type' => [
                    'type' => 'string',
                    'enum' => ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'],
                    'description' => 'Optional: neuer Datentyp.',
                ],
                'precision'     => ['type' => 'integer', 'description' => 'Optional.'],
                'scale'         => ['type' => 'integer', 'description' => 'Optional.'],
                'unit'          => ['type' => 'string', 'description' => 'Optional.'],
                'is_indexed'    => ['type' => 'boolean', 'description' => 'Optional.'],
                'is_nullable'   => ['type' => 'boolean', 'description' => 'Optional.'],
                'default_value' => ['type' => 'string', 'description' => 'Optional.'],
                'transform' => [
                    'type' => 'string',
                    'enum' => ['trim', 'url_decode', 'cast_german_decimal', 'lowercase', 'uppercase', 'strip_tags', 'to_integer', 'to_boolean'],
                    'description' => 'Optional: Transformation beim Import.',
                ],
                'position'      => ['type' => 'integer', 'description' => 'Optional: Sortierposition.'],
                'is_active'     => ['type' => 'boolean', 'description' => 'Optional: Spalte aktiv/inaktiv (wird beim Import berücksichtigt).'],
            ],
            'required' => ['column_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'column_id', DatawarehouseStreamColumn::class, 'NOT_FOUND', 'Spalte nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStreamColumn $column */
            $column = $found['model'];

            $stream = $column->stream;
            if (!$stream || (int)$stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Spalte.');
            }
            if ($stream->isSystem()) {
                return ToolResult::error('VALIDATION_ERROR', 'Spalten von System-Streams können nicht geändert werden.');
            }

            $oldDefinition = [
                'data_type'   => $column->data_type,
                'is_nullable' => (bool)$column->is_nullable,
                'transform'   => $column->transform,
                'precision'   => $column->precision,
                'scale'       => $column->scale,
            ];

            $schemaImpacting = false;

            foreach (['source_key', 'label', 'unit', 'default_value', 'transform'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $value = $arguments[$field];
                    $column->{$field} = $value === '' ? null : $value;
                }
            }
            if (array_key_exists('data_type', $arguments)) {
                $allowedTypes = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'];
                if (!in_array($arguments['data_type'], $allowedTypes, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger data_type.');
                }
                if ($column->data_type !== $arguments['data_type']) {
                    $schemaImpacting = true;
                }
                $column->data_type = $arguments['data_type'];
            }
            foreach (['precision', 'scale', 'position'] as $intField) {
                if (array_key_exists($intField, $arguments)) {
                    $column->{$intField} = $arguments[$intField] !== null ? (int)$arguments[$intField] : null;
                }
            }
            foreach (['is_indexed', 'is_nullable', 'is_active'] as $boolField) {
                if (array_key_exists($boolField, $arguments)) {
                    $newValue = (bool)$arguments[$boolField];
                    if ($boolField === 'is_nullable' && (bool)$column->is_nullable !== $newValue) {
                        $schemaImpacting = true;
                    }
                    $column->{$boolField} = $newValue;
                }
            }

            $column->save();

            $alteredTable = false;
            if ($schemaImpacting && $stream->table_created) {
                try {
                    app(StreamSchemaService::class)->modifyColumn($stream, $column, $oldDefinition, $context->user?->id);
                    $alteredTable = true;
                } catch (\Throwable $e) {
                    return ToolResult::error('EXECUTION_ERROR', 'ALTER TABLE fehlgeschlagen: ' . $e->getMessage());
                }
            }

            return ToolResult::success([
                'id'            => $column->id,
                'stream_id'     => $column->stream_id,
                'column_name'   => $column->column_name,
                'data_type'     => $column->data_type,
                'altered_table' => $alteredTable,
                'team_id'       => $teamId,
                'message'       => 'Spalte aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Spalte: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'stream_columns', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
