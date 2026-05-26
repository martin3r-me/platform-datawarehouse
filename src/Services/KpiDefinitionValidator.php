<?php

namespace Platform\Datawarehouse\Services;

use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;

/**
 * Validates a KPI definition against the same whitelists the KpiQueryBuilder
 * uses at query time. Centralizes the rules so write-paths (UI, API, MCP tools)
 * and the query path agree on what is allowed.
 *
 * The validator does not mutate the input — it only inspects it and returns
 * either null (valid) or a human-readable error message (invalid).
 */
class KpiDefinitionValidator
{
    public const ALLOWED_AGGREGATIONS    = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];
    public const ALLOWED_OPERATORS       = ['=', '!=', '<', '>', '<=', '>=', 'LIKE'];
    public const ALLOWED_TERM_OPERATORS  = ['+', '-', '*', '/'];
    public const ALLOWED_DATE_RANGES     = [
        'current_month', 'current_quarter', 'current_year', 'current_week',
        'last_30_days', 'last_90_days', 'last_12_months',
        'previous_month', 'previous_quarter', 'previous_year',
        'year_to_date',
    ];
    public const ALLOWED_CALENDAR_COLUMNS = [
        'weekday', 'weekday_num', 'is_weekend', 'kw', 'month', 'quarter', 'year',
    ];
    public const ALLOWED_SNAPSHOT_MODES = ['latest', 'all'];
    public const SYSTEM_COLUMNS = ['id', '_snapshot_at', '_imported_at', '_valid_from', '_valid_to', '_is_deleted', '_is_current', 'created_at', 'updated_at'];

    private const COLUMN_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    private const ALIAS_REGEX  = '/^s\d+$/';

    /**
     * Validate a full KPI definition. Returns null on success, or a single
     * descriptive error message on failure (LLM-friendly: tells the caller
     * exactly which key was wrong).
     *
     * Performs DB-backed existence checks: every referenced stream must exist
     * in the given team, every referenced column must exist on that stream.
     */
    public function validate(array $definition, int $teamId): ?string
    {
        $streams = $definition['streams'] ?? null;
        if (!is_array($streams) || empty($streams)) {
            return 'streams[] ist erforderlich (mindestens 1 Eintrag mit stream_id und alias).';
        }

        $resolvedStreams = [];
        foreach ($streams as $i => $streamDef) {
            if (!is_array($streamDef)) {
                return "streams[$i] muss ein Objekt sein.";
            }
            $alias = $streamDef['alias'] ?? null;
            if (!is_string($alias) || !preg_match(self::ALIAS_REGEX, $alias)) {
                return "streams[$i].alias muss dem Muster 's0', 's1', … entsprechen.";
            }
            if (isset($resolvedStreams[$alias])) {
                return "streams[$i].alias '$alias' wird mehrfach verwendet.";
            }

            $streamId = (int)($streamDef['stream_id'] ?? 0);
            if ($streamId <= 0) {
                return "streams[$i].stream_id muss eine positive Integer-ID sein.";
            }

            $stream = DatawarehouseStream::query()
                ->where('team_id', $teamId)
                ->find($streamId);
            if (!$stream) {
                return "Stream {$streamId} nicht gefunden (oder kein Zugriff).";
            }

            if ($i > 0) {
                $join = $streamDef['join'] ?? null;
                if (!is_array($join) || empty($join['relation_id'])) {
                    return "streams[$i].join.relation_id ist bei verknüpften Streams erforderlich.";
                }
                $relation = DatawarehouseStreamRelation::query()
                    ->where('team_id', $teamId)
                    ->find((int)$join['relation_id']);
                if (!$relation) {
                    return "Relation {$join['relation_id']} nicht gefunden (streams[$i].join).";
                }
                $type = strtoupper($join['type'] ?? 'INNER');
                if (!in_array($type, ['INNER', 'LEFT'], true)) {
                    return "streams[$i].join.type muss INNER oder LEFT sein.";
                }
            }

            $resolvedStreams[$alias] = $stream;
        }

        $baseAlias = $streams[0]['alias'];

        $terms = $this->extractAggregationTerms($definition);
        if (empty($terms)) {
            return 'Mindestens ein Aggregations-Term (aggregations[] oder aggregation) ist erforderlich.';
        }

        foreach ($terms as $i => $term) {
            if (!is_array($term)) {
                return "aggregations[$i] muss ein Objekt sein.";
            }
            $function = strtoupper((string)($term['function'] ?? 'SUM'));
            if (!in_array($function, self::ALLOWED_AGGREGATIONS, true)) {
                return "aggregations[$i].function ungültig. Erlaubt: " . implode(', ', self::ALLOWED_AGGREGATIONS) . '.';
            }
            $alias = (string)($term['stream_alias'] ?? $baseAlias);
            if (!isset($resolvedStreams[$alias])) {
                return "aggregations[$i].stream_alias '$alias' ist in streams[] nicht definiert.";
            }
            $column = (string)($term['column'] ?? '*');
            if ($column === '*') {
                if ($function !== 'COUNT') {
                    return "aggregations[$i].column '*' ist nur mit function=COUNT erlaubt.";
                }
            } else {
                if (!preg_match(self::COLUMN_REGEX, $column)) {
                    return "aggregations[$i].column '$column' enthält ungültige Zeichen.";
                }
                if ($error = $this->ensureColumnExists($column, $resolvedStreams[$alias])) {
                    return "aggregations[$i]: $error";
                }
            }
            if ($i > 0) {
                $op = (string)($term['operator'] ?? '+');
                if (!in_array($op, self::ALLOWED_TERM_OPERATORS, true)) {
                    return "aggregations[$i].operator ungültig. Erlaubt: " . implode(', ', self::ALLOWED_TERM_OPERATORS) . '.';
                }
            }
        }

        $filters = $definition['filters'] ?? [];
        if (!is_array($filters)) {
            return 'filters muss ein Array sein.';
        }
        foreach ($filters as $i => $filter) {
            if (!is_array($filter)) {
                return "filters[$i] muss ein Objekt sein.";
            }
            $alias = (string)($filter['stream_alias'] ?? $baseAlias);
            if (!isset($resolvedStreams[$alias])) {
                return "filters[$i].stream_alias '$alias' ist in streams[] nicht definiert.";
            }
            $column = (string)($filter['column'] ?? '');
            if (!preg_match(self::COLUMN_REGEX, $column)) {
                return "filters[$i].column '$column' enthält ungültige Zeichen.";
            }
            $operator = (string)($filter['operator'] ?? '=');
            if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
                return "filters[$i].operator ungültig. Erlaubt: " . implode(', ', self::ALLOWED_OPERATORS) . '.';
            }
            if ($error = $this->ensureColumnExists($column, $resolvedStreams[$alias])) {
                return "filters[$i]: $error";
            }
            if (!array_key_exists('value', $filter)) {
                return "filters[$i].value ist erforderlich.";
            }
        }

        $cal = $definition['calendar_filters'] ?? null;
        if ($cal !== null) {
            if (!is_array($cal)) {
                return 'calendar_filters muss ein Objekt sein.';
            }
            $dateColumn = $cal['date_column'] ?? null;
            if ($dateColumn !== null) {
                if (!is_string($dateColumn) || !preg_match(self::COLUMN_REGEX, $dateColumn)) {
                    return 'calendar_filters.date_column enthält ungültige Zeichen.';
                }
                $dateAlias = (string)($cal['date_stream_alias'] ?? $baseAlias);
                if (!isset($resolvedStreams[$dateAlias])) {
                    return "calendar_filters.date_stream_alias '$dateAlias' ist in streams[] nicht definiert.";
                }
                if ($error = $this->ensureColumnExists($dateColumn, $resolvedStreams[$dateAlias])) {
                    return "calendar_filters: $error";
                }
            }
            if (isset($cal['date_range']) && !in_array($cal['date_range'], self::ALLOWED_DATE_RANGES, true)) {
                return 'calendar_filters.date_range ist ungültig. Erlaubt: ' . implode(', ', self::ALLOWED_DATE_RANGES) . '.';
            }
            $conditions = $cal['conditions'] ?? [];
            if (!is_array($conditions)) {
                return 'calendar_filters.conditions muss ein Array sein.';
            }
            foreach ($conditions as $i => $condition) {
                if (!is_array($condition)) {
                    return "calendar_filters.conditions[$i] muss ein Objekt sein.";
                }
                $col = $condition['column'] ?? '';
                if (!in_array($col, self::ALLOWED_CALENDAR_COLUMNS, true)) {
                    return "calendar_filters.conditions[$i].column ist ungültig. Erlaubt: " . implode(', ', self::ALLOWED_CALENDAR_COLUMNS) . '.';
                }
                $op = $condition['operator'] ?? '=';
                if (!in_array($op, self::ALLOWED_OPERATORS, true)) {
                    return "calendar_filters.conditions[$i].operator ist ungültig.";
                }
                if (!array_key_exists('value', $condition)) {
                    return "calendar_filters.conditions[$i].value ist erforderlich.";
                }
            }
        }

        $snapshotMode = $definition['snapshot_mode'] ?? 'latest';
        if (!in_array($snapshotMode, self::ALLOWED_SNAPSHOT_MODES, true)) {
            return 'snapshot_mode muss "latest" oder "all" sein.';
        }

        return null;
    }

    public function extractAggregationTerms(array $definition): array
    {
        $terms = $definition['aggregations'] ?? null;
        if (is_array($terms) && !empty($terms)) {
            return array_values($terms);
        }
        $single = $definition['aggregation'] ?? null;
        if (is_array($single) && !empty($single)) {
            return [$single];
        }
        return [];
    }

    private function ensureColumnExists(string $column, DatawarehouseStream $stream): ?string
    {
        if (in_array($column, self::SYSTEM_COLUMNS, true)) {
            return null;
        }
        $exists = DatawarehouseStreamColumn::query()
            ->where('stream_id', $stream->id)
            ->where('column_name', $column)
            ->where('is_active', true)
            ->exists();
        if (!$exists) {
            return "Spalte '{$column}' existiert nicht (aktiv) auf Stream '{$stream->name}' (ID {$stream->id}).";
        }
        return null;
    }
}
