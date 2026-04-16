<?php

namespace Platform\Datawarehouse\Services;

class PayloadNormalizer
{
    /**
     * Parse raw request body into an array.
     * Tries: JSON → URL-decoded JSON → null.
     */
    public static function parse(string $rawData): ?array
    {
        if ($rawData === '') {
            return null;
        }

        // 1. Try as-is JSON
        $decoded = json_decode($rawData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // 2. Try URL-decoded JSON (4D clients send URL-encoded JSON with
        //    Content-Type: application/x-www-form-urlencoded)
        $urlDecoded = urldecode($rawData);
        if ($urlDecoded !== $rawData) {
            $decoded = json_decode($urlDecoded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Normalize known payload shapes into a flat array of row objects.
     * Currently recognizes the 4D LIST format: LIST → COLUMNS + ROWS.
     * Falls through unchanged for already-flat payloads.
     */
    public static function normalize(array $data): array
    {
        if (self::isFourDList($data)) {
            return self::normalizeFourDList($data);
        }

        return $data;
    }

    protected static function isFourDList(array $data): bool
    {
        return ($data['name'] ?? null) === 'LIST'
            && isset($data['children'])
            && is_array($data['children']);
    }

    /**
     * Convert a 4D LIST payload to a flat array of row objects.
     *
     * Structure:
     *   { name: "LIST", children: [
     *       { name: "COLUMNS", children: [{ name: "COLUMN", "@caption": "X", "@index": "1" }, ...] },
     *       { name: "ROWS", children: [
     *           { name: "ROW", "@rowType": "ROW", children: [{ "@index": "1", "@value": "v1" }, ...] },
     *           ...
     *       ] },
     *   ] }
     *
     * Output:
     *   [ { "X": "v1", ... }, ... ]
     */
    protected static function normalizeFourDList(array $data): array
    {
        $columns = [];
        $rows = [];

        foreach ($data['children'] ?? [] as $child) {
            $childName = $child['name'] ?? null;

            if ($childName === 'COLUMNS') {
                foreach ($child['children'] ?? [] as $col) {
                    $index   = $col['@index']   ?? null;
                    $caption = $col['@caption'] ?? null;
                    if ($index !== null && $caption !== null) {
                        $columns[(string) $index] = (string) $caption;
                    }
                }
            } elseif ($childName === 'ROWS') {
                foreach ($child['children'] ?? [] as $row) {
                    if (($row['@rowType'] ?? null) !== 'ROW') {
                        continue;
                    }

                    $rowData = [];
                    foreach ($row['children'] ?? [] as $cell) {
                        $index = $cell['@index'] ?? null;
                        if ($index === null) {
                            continue;
                        }
                        $value = $cell['@value'] ?? $cell['value'] ?? null;
                        $key = $columns[(string) $index] ?? ('col_' . $index);
                        $rowData[$key] = $value;
                    }

                    if (!empty($rowData)) {
                        $rows[] = $rowData;
                    }
                }
            }
        }

        return $rows;
    }
}
