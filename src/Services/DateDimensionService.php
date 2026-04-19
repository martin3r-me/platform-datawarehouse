<?php

namespace Platform\Datawarehouse\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DateDimensionService
{
    private const CHUNK_SIZE = 500;
    private const TABLE = 'dw_dim_date';

    private const WEEKDAYS = [
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
        7 => 'Sonntag',
    ];

    /**
     * Fixed NRW holidays (month => day => name).
     */
    private const FIXED_HOLIDAYS = [
        1  => [1  => 'Neujahr'],
        5  => [1  => 'Tag der Arbeit'],
        10 => [3  => 'Tag der Deutschen Einheit'],
        11 => [1  => 'Allerheiligen'],
        12 => [25 => '1. Weihnachtstag', 26 => '2. Weihnachtstag'],
    ];

    /**
     * Seed the dim_date table for the given date range.
     * Idempotent via upsert on date_key.
     */
    public function seed(?string $from = null, ?string $to = null): int
    {
        $start = Carbon::parse($from ?? '2020-01-01');
        $end = Carbon::parse($to ?? '2035-12-31');

        $easterCache = [];
        $rows = [];
        $total = 0;

        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $year = $date->year;

            if (!isset($easterCache[$year])) {
                $easterCache[$year] = $this->calculateEaster($year);
            }

            $rows[] = $this->buildRow($date, $easterCache[$year]);

            if (count($rows) >= self::CHUNK_SIZE) {
                $this->upsertChunk($rows);
                $total += count($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            $this->upsertChunk($rows);
            $total += count($rows);
        }

        return $total;
    }

    /**
     * Seed Schulferien periods.
     * Each period: ['name' => 'Sommerferien', 'from' => '2025-07-14', 'to' => '2025-08-26']
     */
    public function seedSchulferien(array $periods): int
    {
        $updated = 0;

        foreach ($periods as $period) {
            $name = $period['name'];
            $from = Carbon::parse($period['from']);
            $to = Carbon::parse($period['to']);

            $affected = DB::table(self::TABLE)
                ->whereBetween('date_key', [$from->toDateString(), $to->toDateString()])
                ->update([
                    'is_schulferien'  => true,
                    'schulferien_name' => $name,
                ]);

            $updated += $affected;
        }

        return $updated;
    }

    private function buildRow(Carbon $date, Carbon $easter): array
    {
        $weekdayNum = (int) $date->isoWeekday(); // 1=Mon..7=Sun
        $holiday = $this->getHolidayName($date, $easter);

        return [
            'date_key'        => $date->toDateString(),
            'weekday'         => self::WEEKDAYS[$weekdayNum],
            'weekday_num'     => $weekdayNum,
            'is_weekend'      => $weekdayNum >= 6,
            'kw'              => (int) $date->isoWeek(),
            'month'           => (int) $date->month,
            'quarter'         => (int) $date->quarter,
            'year'            => (int) $date->year,
            'is_feiertag'     => $holiday !== null,
            'feiertag_name'   => $holiday,
            'is_schulferien'  => false,
            'schulferien_name' => null,
            'bundesland'      => 'NW',
        ];
    }

    /**
     * Get the NRW holiday name for the given date, or null.
     * 6 fixed + 5 easter-dependent (incl. Fronleichnam, Allerheiligen is fixed).
     */
    private function getHolidayName(Carbon $date, Carbon $easter): ?string
    {
        // Fixed holidays
        $month = $date->month;
        $day = $date->day;

        if (isset(self::FIXED_HOLIDAYS[$month][$day])) {
            return self::FIXED_HOLIDAYS[$month][$day];
        }

        // Easter-dependent holidays (offset in days from Easter Sunday)
        $easterOffsets = [
            -2  => 'Karfreitag',
            0   => 'Ostersonntag',
            1   => 'Ostermontag',
            39  => 'Christi Himmelfahrt',
            49  => 'Pfingstsonntag',
            50  => 'Pfingstmontag',
            60  => 'Fronleichnam',
        ];

        // Signed diff: positive when date is after Easter
        $diffDays = $date->copy()->startOfDay()->dayOfYear - $easter->copy()->startOfDay()->dayOfYear;

        if (isset($easterOffsets[$diffDays])) {
            return $easterOffsets[$diffDays];
        }

        return null;
    }

    /**
     * Calculate Easter Sunday using Meeus/Jones/Butcher algorithm.
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

    private function upsertChunk(array $rows): void
    {
        DB::table(self::TABLE)->upsert(
            $rows,
            ['date_key'],
            [
                'weekday', 'weekday_num', 'is_weekend', 'kw', 'month', 'quarter', 'year',
                'is_feiertag', 'feiertag_name', 'bundesland',
                // Note: is_schulferien and schulferien_name are NOT overwritten on upsert
                // so that manually seeded Schulferien data is preserved.
            ]
        );
    }
}
