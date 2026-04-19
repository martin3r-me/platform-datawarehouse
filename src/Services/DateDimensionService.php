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
     * Seed the dim_date table for the given date range.
     * Idempotent via upsert on date_key.
     */
    public function seed(?string $from = null, ?string $to = null): int
    {
        $start = Carbon::parse($from ?? '2020-01-01');
        $end = Carbon::parse($to ?? '2035-12-31');

        $rows = [];
        $total = 0;

        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $rows[] = $this->buildRow($date);

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

    private function buildRow(Carbon $date): array
    {
        $weekdayNum = (int) $date->isoWeekday(); // 1=Mon..7=Sun

        return [
            'date_key'    => $date->toDateString(),
            'weekday'     => self::WEEKDAYS[$weekdayNum],
            'weekday_num' => $weekdayNum,
            'is_weekend'  => $weekdayNum >= 6,
            'kw'          => (int) $date->isoWeek(),
            'month'       => (int) $date->month,
            'quarter'     => (int) $date->quarter,
            'year'        => (int) $date->year,
        ];
    }

    private function upsertChunk(array $rows): void
    {
        DB::table(self::TABLE)->upsert(
            $rows,
            ['date_key'],
            ['weekday', 'weekday_num', 'is_weekend', 'kw', 'month', 'quarter', 'year']
        );
    }
}
