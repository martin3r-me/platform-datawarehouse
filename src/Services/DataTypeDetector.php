<?php

namespace Platform\Datawarehouse\Services;

class DataTypeDetector
{
    /**
     * Detect data type from a sample value.
     */
    public static function detect(mixed $value): string
    {
        if ($value === null) {
            return 'string';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'decimal';
        }

        if (is_array($value) || is_object($value)) {
            return 'json';
        }

        $str = trim((string) $value);

        if ($str === '') {
            return 'string';
        }

        // Boolean patterns
        if (in_array(strtolower($str), ['true', 'false'], true)) {
            return 'boolean';
        }

        // Integer (pure digits, optionally with leading minus)
        if (preg_match('/^-?\d+$/', $str) && strlen($str) <= 18) {
            return 'integer';
        }

        // Decimal
        if (is_numeric($str) && str_contains($str, '.')) {
            return 'decimal';
        }

        // DateTime (ISO 8601 or common patterns)
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $str)) {
            return 'datetime';
        }

        // Date
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return 'date';
        }

        // Long text (> 255 chars)
        if (mb_strlen($str) > 255) {
            return 'text';
        }

        return 'string';
    }

    /**
     * Detect types for all keys in a sample payload.
     *
     * Examines EVERY row in the payload (not just the first) and reconciles
     * the per-row types into a single safe type per key. This prevents
     * mis-detections when the first sample value happens to be numeric but
     * later rows contain alphanumeric strings (e.g. a "kostenstelle" field
     * that contains both "1234" and "ABL").
     *
     * Returns ['key' => 'type', ...].
     */
    public static function detectFromPayload(array $payload): array
    {
        $rows = self::extractRows($payload);
        if (empty($rows)) {
            return [];
        }

        // Collect all distinct non-null types observed per key.
        $typesPerKey = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $key => $value) {
                // Skip null / empty-string samples: they carry no type info.
                if ($value === null || $value === '') {
                    continue;
                }
                $typesPerKey[$key][self::detect($value)] = true;
            }
        }

        // Preserve the key order of the first row; include any keys that
        // only appeared in later rows at the end.
        $firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];
        $orderedKeys = array_values(array_unique(array_merge(
            array_keys($firstRow),
            array_keys($typesPerKey),
        )));

        $result = [];
        foreach ($orderedKeys as $key) {
            $observed = array_keys($typesPerKey[$key] ?? []);
            $result[$key] = self::reconcileTypes($observed);
        }

        return $result;
    }

    /**
     * Unwrap a payload into a list of row arrays.
     *
     *   [{...}, {...}]             → list of rows
     *   {data: [{...}]}            → list of rows (also 'rows', 'items', 'records')
     *   {foo: 1, bar: 2}           → single-row list
     */
    protected static function extractRows(array $payload): array
    {
        // Already a list of rows.
        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload;
        }

        // Unwrap common wrapper keys.
        foreach (['data', 'rows', 'items', 'records'] as $wrapper) {
            if (isset($payload[$wrapper]) && is_array($payload[$wrapper])) {
                $inner = $payload[$wrapper];
                if (isset($inner[0]) && is_array($inner[0])) {
                    return $inner;
                }
                return [$inner];
            }
        }

        // Treat as a single row.
        return [$payload];
    }

    /**
     * Reduce a set of observed types into a single safe target type.
     *
     * Compatible widenings:
     *   {integer, decimal}         → decimal
     *   {date, datetime}           → datetime
     *   {string, text}             → text
     *
     * Anything else conflicting falls back to 'string'.
     */
    protected static function reconcileTypes(array $types): string
    {
        if (empty($types)) {
            return 'string';
        }
        if (count($types) === 1) {
            return $types[0];
        }

        // Numeric widening.
        if (count(array_diff($types, ['integer', 'decimal'])) === 0) {
            return 'decimal';
        }

        // Temporal widening.
        if (count(array_diff($types, ['date', 'datetime'])) === 0) {
            return 'datetime';
        }

        // Textual widening.
        if (count(array_diff($types, ['string', 'text'])) === 0) {
            return 'text';
        }

        // Mixed / incompatible — string is the safest container.
        return 'string';
    }
}
