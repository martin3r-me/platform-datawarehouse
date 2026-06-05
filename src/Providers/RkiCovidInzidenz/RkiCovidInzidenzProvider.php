<?php

namespace Platform\Datawarehouse\Providers\RkiCovidInzidenz;

use Illuminate\Support\Facades\Http;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * RKI COVID-19 7-Tage-Inzidenz provider.
 *
 * Reads daily-updated CSV files from the RKI GitHub repository.
 * Three endpoints for different granularities: Deutschland, Bundesländer, Landkreise.
 * No authentication required.
 */
class RkiCovidInzidenzProvider implements PullProvider
{
    private const BASE_URL = 'https://raw.githubusercontent.com/robert-koch-institut/COVID-19_7-Tage-Inzidenz_in_Deutschland/main/';

    private const CSV_FILES = [
        'deutschland'  => 'COVID-19-Faelle_7-Tage-Inzidenz_Deutschland.csv',
        'bundeslaender' => 'COVID-19-Faelle_7-Tage-Inzidenz_Bundeslaender.csv',
        'landkreise'   => 'COVID-19-Faelle_7-Tage-Inzidenz_Landkreise.csv',
    ];

    private const PAGE_SIZE = 5000;

    public function key(): string
    {
        return 'rki_covid_inzidenz';
    }

    public function label(): string
    {
        return 'RKI COVID-19 Inzidenz';
    }

    public function description(): ?string
    {
        return 'Tagesaktuelle 7-Tage-Inzidenz vom Robert Koch-Institut (GitHub CSV). Drei Granularitäten: Deutschland, Bundesländer, Landkreise.';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'deutschland' => new Endpoint(
                key: 'deutschland',
                label: 'Deutschland (gesamt)',
                description: 'Bundesweite Inzidenz mit Altersgruppen. ~16.000 Zeilen. Filter: altersgruppe.',
                paginated: true,
                incrementalField: 'Meldedatum',
                defaultStrategy: 'append',
                naturalKey: 'row_id',
                supportedStrategies: ['append'],
            ),
            'bundeslaender' => new Endpoint(
                key: 'bundeslaender',
                label: 'Bundesländer',
                description: 'Inzidenz pro Bundesland mit Altersgruppen. ~262.000 Zeilen. Filter: altersgruppe, bundesland_id.',
                paginated: true,
                incrementalField: 'Meldedatum',
                defaultStrategy: 'append',
                naturalKey: 'row_id',
                supportedStrategies: ['append'],
            ),
            'landkreise' => new Endpoint(
                key: 'landkreise',
                label: 'Landkreise',
                description: 'Inzidenz pro Landkreis (keine Altersgruppen). ~963.000 Zeilen. Filter: bundesland_id, landkreis_id.',
                paginated: true,
                incrementalField: 'Meldedatum',
                defaultStrategy: 'append',
                naturalKey: 'row_id',
                supportedStrategies: ['append'],
            ),
        ];
    }

    public function testConnection(DatawarehouseConnection $connection): bool
    {
        $response = Http::timeout(15)->head(self::BASE_URL . self::CSV_FILES['deutschland']);

        return $response->successful();
    }

    public function fetch(PullContext $context): PullResult
    {
        $endpointKey = $context->endpoint;
        $csvFile = self::CSV_FILES[$endpointKey] ?? null;

        if (! $csvFile) {
            return new PullResult(rows: [], nextCursor: null);
        }

        $config = $context->stream->pull_config ?? [];
        $cursor = $context->cursor ? (int) $context->cursor : 0;

        // Fetch CSV (full file, filter in-memory)
        $csvContent = $this->fetchCsv($csvFile);
        if ($csvContent === null) {
            return new PullResult(rows: [], nextCursor: null);
        }

        $allRows = $this->parseCsv($csvContent, $endpointKey);

        // Apply incremental filter (since date)
        if ($context->since) {
            $sinceDate = $context->since;
            $allRows = array_filter($allRows, function (array $row) use ($sinceDate) {
                return ($row['Meldedatum'] ?? '') >= $sinceDate;
            });
            $allRows = array_values($allRows);
        }

        // Apply pull_config filters
        $allRows = $this->applyFilters($allRows, $config, $endpointKey);

        // Generate synthetic row_id and paginate
        $totalRows = count($allRows);
        $pageRows = array_slice($allRows, $cursor, self::PAGE_SIZE);

        // Add row_id (natural key)
        $pageRows = array_map(function (array $row) use ($endpointKey) {
            $row['row_id'] = $this->buildRowId($row, $endpointKey);
            return $row;
        }, $pageRows);

        $nextCursor = ($cursor + self::PAGE_SIZE < $totalRows)
            ? (string) ($cursor + self::PAGE_SIZE)
            : null;

        return new PullResult(
            rows: $pageRows,
            nextCursor: $nextCursor,
            meta: [
                'total_filtered' => $totalRows,
                'page_offset' => $cursor,
                'page_size' => count($pageRows),
            ],
        );
    }

    private function fetchCsv(string $filename): ?string
    {
        $response = Http::timeout(120)->get(self::BASE_URL . $filename);

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    private function parseCsv(string $csv, string $endpointKey): array
    {
        $lines = explode("\n", $csv);
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', $header);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line);
            if (count($values) !== count($header)) {
                continue;
            }

            $row = array_combine($header, $values);
            if ($row === false) {
                continue;
            }

            // Normalize numeric fields
            foreach (['Bevoelkerung', 'Faelle_gesamt', 'Faelle_neu', 'Faelle_7-Tage'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = is_numeric($row[$field]) ? (int) $row[$field] : $row[$field];
                }
            }
            if (isset($row['Inzidenz_7-Tage'])) {
                $row['Inzidenz_7-Tage'] = is_numeric($row['Inzidenz_7-Tage']) ? (float) $row['Inzidenz_7-Tage'] : $row['Inzidenz_7-Tage'];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function applyFilters(array $rows, array $config, string $endpointKey): array
    {
        // Altersgruppe filter (deutschland + bundeslaender only)
        if (in_array($endpointKey, ['deutschland', 'bundeslaender'])) {
            $altersgruppe = $config['altersgruppe'] ?? '00+';
            if ($altersgruppe !== 'all') {
                $rows = array_filter($rows, function (array $row) use ($altersgruppe) {
                    return ($row['Altersgruppe'] ?? '') === $altersgruppe;
                });
            }
        }

        // Bundesland filter (bundeslaender + landkreise)
        if (in_array($endpointKey, ['bundeslaender', 'landkreise']) && ! empty($config['bundesland_id'])) {
            $blId = (string) $config['bundesland_id'];
            $rows = array_filter($rows, function (array $row) use ($blId, $endpointKey) {
                $field = $endpointKey === 'bundeslaender' ? 'Bundesland_id' : 'Landkreis_id';
                $value = (string) ($row[$field] ?? '');
                if ($endpointKey === 'landkreise') {
                    // Landkreis_id is 5-digit, first 2 digits = Bundesland
                    return str_starts_with($value, $blId);
                }
                return $value === $blId;
            });
        }

        // Landkreis filter (landkreise only)
        if ($endpointKey === 'landkreise' && ! empty($config['landkreis_id'])) {
            $lkId = (string) $config['landkreis_id'];
            $rows = array_filter($rows, function (array $row) use ($lkId) {
                return ((string) ($row['Landkreis_id'] ?? '')) === $lkId;
            });
        }

        return array_values($rows);
    }

    private function buildRowId(array $row, string $endpointKey): string
    {
        $parts = [$row['Meldedatum'] ?? 'unknown'];

        switch ($endpointKey) {
            case 'deutschland':
                $parts[] = $row['Altersgruppe'] ?? '00+';
                break;
            case 'bundeslaender':
                $parts[] = $row['Bundesland_id'] ?? '00';
                $parts[] = $row['Altersgruppe'] ?? '00+';
                break;
            case 'landkreise':
                $parts[] = $row['Landkreis_id'] ?? '00000';
                break;
        }

        return implode('_', $parts);
    }
}
