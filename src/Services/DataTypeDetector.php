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
     * Returns ['key' => 'type', ...].
     */
    public static function detectFromPayload(array $payload): array
    {
        // Normalize: if array of rows, use first row
        $row = (isset($payload[0]) && is_array($payload[0])) ? $payload[0] : $payload;

        // Unwrap common wrapper keys
        foreach (['data', 'rows', 'items', 'records'] as $wrapper) {
            if (isset($row[$wrapper]) && is_array($row[$wrapper])) {
                $inner = $row[$wrapper];
                $row = (isset($inner[0]) && is_array($inner[0])) ? $inner[0] : $inner;
                break;
            }
        }

        $types = [];
        foreach ($row as $key => $value) {
            $types[$key] = self::detect($value);
        }

        return $types;
    }
}
