<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Sets (replaces) a stream's exclusion rules. Rows matching ANY rule are
 * removed from every KPI calculation on that stream. Rules are data, so a
 * year-end agreement change is just an updated rule set — takes effect
 * immediately (applied at query time by KpiQueryBuilder), no re-import needed.
 */
class SetStreamExclusionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    private const ALLOWED_OPS = ['contains', 'equals', 'lt', 'lte', 'gt', 'gte', 'empty'];
    private const COLUMN_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    private const MAX_RULES = 100;

    public function getName(): string
    {
        return 'datawarehouse.stream_exclusions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/streams/{id}/exclusions - Setzt (ersetzt) die Ausschluss-Regeln eines Streams. Zeilen, die IRGENDEINE Regel erfüllen, zählen in KEINER KPI dieses Streams mit ("bereinigt"). ERFORDERLICH: stream_id, rules (Array). Regel: { field (aktive Spalte), op (contains|equals|lt|lte|gt|gte|empty), value, note? }. Wirkt sofort (Query-Zeit), kein Re-Import nötig. Leeres Array = alle Ausschlüsse entfernen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'stream_id' => ['type' => 'integer', 'description' => 'ID des Streams (ERFORDERLICH).'],
                'rules'     => [
                    'type' => 'array',
                    'description' => 'Vollständige Regel-Liste (ersetzt die bestehende). Leeres Array entfernt alle.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string', 'description' => 'Spaltenname (aktive Stream-Spalte).'],
                            'op'    => ['type' => 'string', 'enum' => self::ALLOWED_OPS],
                            'value' => ['description' => 'Vergleichswert (bei op=empty ignoriert).'],
                            'note'  => ['type' => 'string', 'description' => 'Optionaler Hinweis (z. B. Grund/Vereinbarung).'],
                        ],
                        'required' => ['field', 'op'],
                    ],
                ],
            ],
            'required' => ['stream_id', 'rules'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'stream_id', DatawarehouseStream::class, 'NOT_FOUND', 'Stream nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int) $stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }

            $rules = $arguments['rules'] ?? null;
            if (!is_array($rules)) {
                return ToolResult::error('VALIDATION_ERROR', 'rules muss ein Array sein (leeres Array = alle Ausschlüsse entfernen).');
            }
            if (count($rules) > self::MAX_RULES) {
                return ToolResult::error('VALIDATION_ERROR', 'Zu viele Regeln (max ' . self::MAX_RULES . ').');
            }

            $activeColumns = $stream->columns()->where('is_active', true)->pluck('column_name')->all();

            $clean = [];
            foreach (array_values($rules) as $i => $rule) {
                if (!is_array($rule)) {
                    return ToolResult::error('VALIDATION_ERROR', "rules[$i] muss ein Objekt sein.");
                }
                $field = $rule['field'] ?? null;
                $op = strtolower((string) ($rule['op'] ?? ''));

                if (!is_string($field) || !preg_match(self::COLUMN_REGEX, $field)) {
                    return ToolResult::error('VALIDATION_ERROR', "rules[$i].field ungültig.");
                }
                if (!in_array($field, $activeColumns, true)) {
                    return ToolResult::error('VALIDATION_ERROR', "rules[$i].field '{$field}' ist keine aktive Spalte dieses Streams.");
                }
                if (!in_array($op, self::ALLOWED_OPS, true)) {
                    return ToolResult::error('VALIDATION_ERROR', "rules[$i].op ungültig. Erlaubt: " . implode(', ', self::ALLOWED_OPS) . '.');
                }
                if ($op !== 'empty' && !array_key_exists('value', $rule)) {
                    return ToolResult::error('VALIDATION_ERROR', "rules[$i].value ist erforderlich (außer bei op=empty).");
                }

                $entry = ['field' => $field, 'op' => $op];
                if ($op !== 'empty') {
                    $entry['value'] = $rule['value'];
                }
                if (!empty($rule['note'])) {
                    $entry['note'] = (string) $rule['note'];
                }
                $clean[] = $entry;
            }

            $stream->update(['exclusions' => $clean]);

            return ToolResult::success([
                'stream_id'  => $stream->id,
                'exclusions' => $clean,
                'count'      => count($clean),
                'team_id'    => $teamId,
                'message'    => 'Ausschluss-Regeln gesetzt — wirken sofort auf alle KPIs dieses Streams. Prüfe mit "datawarehouse.stream.preview" (Filter) oder "datawarehouse.kpis.preview".',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Setzen der Ausschlüsse: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'exclusions'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
