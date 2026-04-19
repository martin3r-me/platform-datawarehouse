<?php

namespace Platform\Datawarehouse\Providers\Feiertage;

use Carbon\Carbon;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal provider for DACH public holidays (Feiertage).
 *
 * No external API — holidays are computed from fixed dates and Easter.
 * Covers DE (all 16 Bundesländer), AT (national), CH (national/Bundesfeiertage).
 */
class FeiertageProvider implements PullProvider
{
    /**
     * Fixed holidays per country.
     * Format: [month, day, name, land_ids, bundesland_ids (null = nationwide)]
     */
    private const FIXED_HOLIDAYS_DE = [
        ['month' => 1,  'day' => 1,  'name' => 'Neujahr'],
        ['month' => 5,  'day' => 1,  'name' => 'Tag der Arbeit'],
        ['month' => 10, 'day' => 3,  'name' => 'Tag der Deutschen Einheit'],
        ['month' => 12, 'day' => 25, 'name' => '1. Weihnachtstag'],
        ['month' => 12, 'day' => 26, 'name' => '2. Weihnachtstag'],
    ];

    /**
     * Bundesland-specific fixed holidays in DE.
     * [month, day, name, [bundesland_ids]]
     */
    private const FIXED_HOLIDAYS_DE_REGIONAL = [
        ['month' => 1,  'day' => 6,  'name' => 'Heilige Drei Könige',          'bundeslaender' => ['BW', 'BY', 'ST']],
        ['month' => 3,  'day' => 8,  'name' => 'Internationaler Frauentag',    'bundeslaender' => ['BE']],
        ['month' => 8,  'day' => 15, 'name' => 'Mariä Himmelfahrt',            'bundeslaender' => ['BY', 'SL']],
        ['month' => 9,  'day' => 20, 'name' => 'Weltkindertag',                'bundeslaender' => ['TH']],
        ['month' => 10, 'day' => 31, 'name' => 'Reformationstag',              'bundeslaender' => ['BB', 'HB', 'HH', 'MV', 'NI', 'SN', 'ST', 'SH', 'TH']],
        ['month' => 11, 'day' => 1,  'name' => 'Allerheiligen',                'bundeslaender' => ['BW', 'BY', 'NW', 'RP', 'SL']],
    ];

    /**
     * Easter-dependent holidays for DE (nationwide).
     */
    private const EASTER_OFFSETS_DE = [
        -2  => 'Karfreitag',
        0   => 'Ostersonntag',
        1   => 'Ostermontag',
        39  => 'Christi Himmelfahrt',
        49  => 'Pfingstsonntag',
        50  => 'Pfingstmontag',
    ];

    /**
     * Easter-dependent holidays for specific DE Bundesländer.
     */
    private const EASTER_OFFSETS_DE_REGIONAL = [
        60 => ['name' => 'Fronleichnam', 'bundeslaender' => ['BW', 'BY', 'HE', 'NW', 'RP', 'SL', 'SN', 'TH']],
    ];

    /**
     * Fixed holidays for AT (national).
     */
    private const FIXED_HOLIDAYS_AT = [
        ['month' => 1,  'day' => 1,  'name' => 'Neujahr'],
        ['month' => 1,  'day' => 6,  'name' => 'Heilige Drei Könige'],
        ['month' => 5,  'day' => 1,  'name' => 'Staatsfeiertag'],
        ['month' => 8,  'day' => 15, 'name' => 'Mariä Himmelfahrt'],
        ['month' => 10, 'day' => 26, 'name' => 'Nationalfeiertag'],
        ['month' => 11, 'day' => 1,  'name' => 'Allerheiligen'],
        ['month' => 12, 'day' => 8,  'name' => 'Mariä Empfängnis'],
        ['month' => 12, 'day' => 25, 'name' => 'Christtag'],
        ['month' => 12, 'day' => 26, 'name' => 'Stefanitag'],
    ];

    private const EASTER_OFFSETS_AT = [
        1   => 'Ostermontag',
        39  => 'Christi Himmelfahrt',
        50  => 'Pfingstmontag',
        60  => 'Fronleichnam',
    ];

    /**
     * Fixed holidays for CH (Bundesfeiertage / national).
     */
    private const FIXED_HOLIDAYS_CH = [
        ['month' => 1,  'day' => 1,  'name' => 'Neujahr'],
        ['month' => 8,  'day' => 1,  'name' => 'Bundesfeiertag'],
        ['month' => 12, 'day' => 25, 'name' => 'Weihnachtstag'],
    ];

    private const EASTER_OFFSETS_CH = [
        -2  => 'Karfreitag',
        1   => 'Ostermontag',
        39  => 'Auffahrt',
        50  => 'Pfingstmontag',
    ];

    private const DE_BUNDESLAENDER = [
        'BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV',
        'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH',
    ];

    public function key(): string
    {
        return 'feiertage';
    }

    public function label(): string
    {
        return 'Feiertage DACH';
    }

    public function description(): ?string
    {
        return 'Gesetzliche Feiertage für DE (alle 16 Bundesländer), AT und CH (berechnet).';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'feiertage' => new Endpoint(
                key: 'feiertage',
                label: 'Feiertage',
                description: 'Alle gesetzlichen Feiertage für DACH-Länder mit Bundesland-Zuordnung.',
                paginated: false,
                incrementalField: null,
                defaultStrategy: 'current',
                naturalKey: 'id',
                supportedStrategies: ['current', 'snapshot'],
            ),
        ];
    }

    public function testConnection(DatawarehouseConnection $connection): bool
    {
        return true;
    }

    public function fetch(PullContext $context): PullResult
    {
        $config = $context->stream->pull_config ?? [];
        $fromYear = (int) ($config['from_year'] ?? now()->year - 2);
        $toYear = (int) ($config['to_year'] ?? now()->year + 2);

        $rows = [];
        $seenIds = [];

        for ($year = $fromYear; $year <= $toYear; $year++) {
            $easter = $this->calculateEaster($year);

            $this->addDeHolidays($year, $easter, $rows, $seenIds);
            $this->addAtHolidays($year, $easter, $rows, $seenIds);
            $this->addChHolidays($year, $easter, $rows, $seenIds);
        }

        return new PullResult(
            rows: $rows,
            nextCursor: null,
            seenExternalIds: $seenIds,
            meta: ['from_year' => $fromYear, 'to_year' => $toYear, 'total' => count($rows)],
        );
    }

    private function addDeHolidays(int $year, Carbon $easter, array &$rows, array &$seenIds): void
    {
        // Nationwide fixed holidays — one row per Bundesland
        foreach (self::FIXED_HOLIDAYS_DE as $h) {
            $date = Carbon::createFromDate($year, $h['month'], $h['day']);
            foreach (self::DE_BUNDESLAENDER as $bl) {
                $id = "{$date->toDateString()}_DE_{$bl}";
                $rows[] = $this->buildRow($id, $date, $h['name'], 'DE', $bl, 'fest', $year);
                $seenIds[] = $id;
            }
        }

        // Regional fixed holidays — only for specified Bundesländer
        foreach (self::FIXED_HOLIDAYS_DE_REGIONAL as $h) {
            $date = Carbon::createFromDate($year, $h['month'], $h['day']);
            foreach ($h['bundeslaender'] as $bl) {
                $id = "{$date->toDateString()}_DE_{$bl}";
                $rows[] = $this->buildRow($id, $date, $h['name'], 'DE', $bl, 'fest', $year);
                $seenIds[] = $id;
            }
        }

        // Nationwide easter-dependent holidays — one row per Bundesland
        foreach (self::EASTER_OFFSETS_DE as $offset => $name) {
            $date = $easter->copy()->addDays($offset);
            foreach (self::DE_BUNDESLAENDER as $bl) {
                $id = "{$date->toDateString()}_DE_{$bl}";
                $rows[] = $this->buildRow($id, $date, $name, 'DE', $bl, 'beweglich', $year);
                $seenIds[] = $id;
            }
        }

        // Regional easter-dependent holidays
        foreach (self::EASTER_OFFSETS_DE_REGIONAL as $offset => $def) {
            $date = $easter->copy()->addDays($offset);
            foreach ($def['bundeslaender'] as $bl) {
                $id = "{$date->toDateString()}_DE_{$bl}";
                $rows[] = $this->buildRow($id, $date, $def['name'], 'DE', $bl, 'beweglich', $year);
                $seenIds[] = $id;
            }
        }
    }

    private function addAtHolidays(int $year, Carbon $easter, array &$rows, array &$seenIds): void
    {
        foreach (self::FIXED_HOLIDAYS_AT as $h) {
            $date = Carbon::createFromDate($year, $h['month'], $h['day']);
            $id = "{$date->toDateString()}_AT";
            $rows[] = $this->buildRow($id, $date, $h['name'], 'AT', null, 'fest', $year);
            $seenIds[] = $id;
        }

        foreach (self::EASTER_OFFSETS_AT as $offset => $name) {
            $date = $easter->copy()->addDays($offset);
            $id = "{$date->toDateString()}_AT";
            $rows[] = $this->buildRow($id, $date, $name, 'AT', null, 'beweglich', $year);
            $seenIds[] = $id;
        }
    }

    private function addChHolidays(int $year, Carbon $easter, array &$rows, array &$seenIds): void
    {
        foreach (self::FIXED_HOLIDAYS_CH as $h) {
            $date = Carbon::createFromDate($year, $h['month'], $h['day']);
            $id = "{$date->toDateString()}_CH";
            $rows[] = $this->buildRow($id, $date, $h['name'], 'CH', null, 'fest', $year);
            $seenIds[] = $id;
        }

        foreach (self::EASTER_OFFSETS_CH as $offset => $name) {
            $date = $easter->copy()->addDays($offset);
            $id = "{$date->toDateString()}_CH";
            $rows[] = $this->buildRow($id, $date, $name, 'CH', null, 'beweglich', $year);
            $seenIds[] = $id;
        }
    }

    private function buildRow(string $id, Carbon $date, string $name, string $landId, ?string $bundeslandId, string $type, int $year): array
    {
        return [
            'id'            => $id,
            'date'          => $date->toDateString(),
            'name'          => $name,
            'land_id'       => $landId,
            'bundesland_id' => $bundeslandId,
            'type'          => $type,
            'year'          => $year,
        ];
    }

    /**
     * Meeus/Jones/Butcher algorithm for Easter Sunday.
     */
    private function calculateEaster(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::createFromDate($year, $month, $day)->startOfDay();
    }
}
