<?php

namespace Platform\Datawarehouse\Providers\FeiertagNrw;

use Carbon\Carbon;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal provider for NRW public holidays (Feiertage).
 *
 * No external API — holidays are computed from fixed dates and Easter.
 * Exposes one endpoint "feiertage" that returns all NRW holidays for
 * a configurable year range (default: current year ± 2).
 */
class FeiertagNrwProvider implements PullProvider
{
    private const FIXED_HOLIDAYS = [
        ['month' => 1,  'day' => 1,  'name' => 'Neujahr'],
        ['month' => 5,  'day' => 1,  'name' => 'Tag der Arbeit'],
        ['month' => 10, 'day' => 3,  'name' => 'Tag der Deutschen Einheit'],
        ['month' => 11, 'day' => 1,  'name' => 'Allerheiligen'],
        ['month' => 12, 'day' => 25, 'name' => '1. Weihnachtstag'],
        ['month' => 12, 'day' => 26, 'name' => '2. Weihnachtstag'],
    ];

    private const EASTER_OFFSETS = [
        -2  => 'Karfreitag',
        0   => 'Ostersonntag',
        1   => 'Ostermontag',
        39  => 'Christi Himmelfahrt',
        49  => 'Pfingstsonntag',
        50  => 'Pfingstmontag',
        60  => 'Fronleichnam',
    ];

    public function key(): string
    {
        return 'feiertag_nrw';
    }

    public function label(): string
    {
        return 'Feiertage NRW';
    }

    public function description(): ?string
    {
        return 'Gesetzliche Feiertage in Nordrhein-Westfalen (berechnet).';
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
                description: 'Alle gesetzlichen Feiertage NRW (6 feste + 7 osterabhängige pro Jahr).',
                paginated: false,
                incrementalField: null,
                defaultStrategy: 'current',
                naturalKey: 'id',
                supportedStrategies: ['current', 'snapshot'],
                meta: ['bundesland' => 'NW'],
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

            // Fixed holidays
            foreach (self::FIXED_HOLIDAYS as $h) {
                $date = Carbon::createFromDate($year, $h['month'], $h['day']);
                $id = $date->toDateString();
                $rows[] = $this->buildRow($id, $date, $h['name']);
                $seenIds[] = $id;
            }

            // Easter-dependent holidays
            foreach (self::EASTER_OFFSETS as $offset => $name) {
                $date = $easter->copy()->addDays($offset);
                $id = $date->toDateString();
                $rows[] = $this->buildRow($id, $date, $name);
                $seenIds[] = $id;
            }
        }

        return new PullResult(
            rows: $rows,
            nextCursor: null,
            seenExternalIds: $seenIds,
            meta: ['from_year' => $fromYear, 'to_year' => $toYear, 'total' => count($rows)],
        );
    }

    private function buildRow(string $id, Carbon $date, string $name): array
    {
        return [
            'id'          => $id,
            'date'        => $date->toDateString(),
            'name'        => $name,
            'weekday'     => $this->weekdayName($date),
            'weekday_num' => $date->isoWeekday(),
            'kw'          => $date->isoWeek(),
            'month'       => $date->month,
            'quarter'     => $date->quarter,
            'year'        => $date->year,
            'bundesland'  => 'NW',
            'type'        => $this->isEasterDependent($name) ? 'beweglich' : 'fest',
        ];
    }

    private function isEasterDependent(string $name): bool
    {
        return in_array($name, array_values(self::EASTER_OFFSETS), true);
    }

    private function weekdayName(Carbon $date): string
    {
        return match ($date->isoWeekday()) {
            1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
            5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
        };
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
