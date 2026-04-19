<?php

namespace Platform\Datawarehouse\Providers\SchulferienNrw;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal provider for NRW school holidays (Schulferien).
 *
 * No external API — periods are maintained as a static config. Each fetch
 * expands the configured periods into individual date rows so they can be
 * used as a stream dimension (one row per holiday day).
 *
 * Periods are sourced from official MSB NRW publications.
 * Update the PERIODS constant when new years are announced.
 */
class SchulferienNrwProvider implements PullProvider
{
    /**
     * NRW Schulferien periods.
     * Source: Ministerium für Schule und Bildung NRW.
     *
     * Format: [name, from (inclusive), to (inclusive)]
     */
    private const PERIODS = [
        // 2024
        ['Winterferien',    '2024-01-08', '2024-01-08'],
        ['Osterferien',     '2024-03-25', '2024-04-06'],
        ['Pfingstferien',   '2024-05-21', '2024-05-21'],
        ['Sommerferien',    '2024-07-08', '2024-08-20'],
        ['Herbstferien',    '2024-10-14', '2024-10-26'],
        ['Weihnachtsferien','2024-12-23', '2025-01-06'],
        // 2025
        ['Osterferien',     '2025-04-14', '2025-04-26'],
        ['Pfingstferien',   '2025-06-10', '2025-06-10'],
        ['Sommerferien',    '2025-07-14', '2025-08-26'],
        ['Herbstferien',    '2025-10-13', '2025-10-25'],
        ['Weihnachtsferien','2025-12-22', '2026-01-06'],
        // 2026
        ['Osterferien',     '2026-03-30', '2026-04-11'],
        ['Pfingstferien',   '2026-05-26', '2026-05-26'],
        ['Sommerferien',    '2026-07-06', '2026-08-18'],
        ['Herbstferien',    '2026-10-19', '2026-10-31'],
        ['Weihnachtsferien','2026-12-21', '2027-01-05'],
        // 2027
        ['Osterferien',     '2027-03-22', '2027-04-03'],
        ['Sommerferien',    '2027-06-28', '2027-08-10'],
        ['Herbstferien',    '2027-10-11', '2027-10-23'],
        ['Weihnachtsferien','2027-12-23', '2028-01-07'],
        // 2028
        ['Osterferien',     '2028-04-10', '2028-04-22'],
        ['Pfingstferien',   '2028-05-30', '2028-05-30'],
        ['Sommerferien',    '2028-07-10', '2028-08-22'],
        ['Herbstferien',    '2028-10-23', '2028-11-04'],
        ['Weihnachtsferien','2028-12-21', '2029-01-05'],
    ];

    public function key(): string
    {
        return 'schulferien_nrw';
    }

    public function label(): string
    {
        return 'Schulferien NRW';
    }

    public function description(): ?string
    {
        return 'Schulferien in Nordrhein-Westfalen (statisch gepflegt).';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'schulferien' => new Endpoint(
                key: 'schulferien',
                label: 'Schulferien',
                description: 'Schulferientage NRW — ein Datensatz pro Ferientag.',
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
        $rows = [];
        $seenIds = [];

        foreach (self::PERIODS as [$name, $from, $to]) {
            $period = CarbonPeriod::create($from, $to);

            foreach ($period as $date) {
                $id = $date->toDateString();

                // Avoid duplicates when periods overlap (e.g. Weihnachtsferien crossing year boundary)
                if (isset($seenIds[$id])) {
                    continue;
                }

                $rows[] = [
                    'id'               => $id,
                    'date'             => $date->toDateString(),
                    'ferien_name'      => $name,
                    'weekday'          => $this->weekdayName($date),
                    'weekday_num'      => $date->isoWeekday(),
                    'is_weekend'       => $date->isoWeekday() >= 6,
                    'kw'               => $date->isoWeek(),
                    'month'            => $date->month,
                    'quarter'          => $date->quarter,
                    'year'             => $date->year,
                    'school_year'      => $this->schoolYear($date),
                    'bundesland'       => 'NW',
                    'period_start'     => $from,
                    'period_end'       => $to,
                ];

                $seenIds[$id] = true;
            }
        }

        return new PullResult(
            rows: $rows,
            nextCursor: null,
            seenExternalIds: array_keys($seenIds),
            meta: ['periods' => count(self::PERIODS), 'total_days' => count($rows)],
        );
    }

    /**
     * Determine school year, e.g. "2024/2025" for dates from Aug 2024 to Jul 2025.
     */
    private function schoolYear(Carbon $date): string
    {
        $year = $date->year;
        $month = $date->month;

        // School year starts August 1
        if ($month >= 8) {
            return $year . '/' . ($year + 1);
        }

        return ($year - 1) . '/' . $year;
    }

    private function weekdayName(Carbon $date): string
    {
        return match ($date->isoWeekday()) {
            1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
            5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
        };
    }
}
