<?php

namespace Platform\Datawarehouse\Services;

use Carbon\CarbonInterface;
use Cron\CronExpression;

/**
 * Decides whether a KPI is due for a scheduler snapshot.
 *
 * The scheduler ticks every minute, but recomputing every KPI on every tick
 * is pure waste: a KPI whose streams import once a day cannot change 1439
 * times in between. Each KPI therefore carries a `snapshot_schedule`:
 *
 *   auto (default)  — recompute when the underlying streams got new data,
 *                     when the calendar day flipped under a rolling date
 *                     range, or when the cached value is older than
 *                     MAX_AGE_HOURS. Adapts itself to the source's rhythm.
 *   <shortcut|cron> — fixed cadence, same syntax as a stream's pull_schedule
 *                     (every_minute, hourly, "0 6 * * *", ...).
 *   manual          — never touched by the scheduler; refreshed only by a
 *                     dashboard load, the UI button or the execute tool.
 *
 * This class is deliberately free of DB access — the caller passes in the
 * few facts it needs, which keeps the decision unit-testable and lets the
 * command resolve the stream timestamps for all KPIs in a single query.
 */
final class KpiSnapshotSchedule
{
    public const AUTO = 'auto';
    public const MANUAL = 'manual';
    public const DEFAULT = self::AUTO;

    /**
     * Upper bound for auto mode: no KPI stays unrecomputed longer than this,
     * even when no source moved. Guards against a silently frozen tile when
     * a stream stops delivering.
     */
    public const MAX_AGE_HOURS = 24;

    /**
     * Minimum gap between two snapshots of the same KPI. Overlapping
     * scheduler ticks (or a manual refresh right before a tick) must not
     * produce duplicate rows in the same minute.
     */
    private const MIN_GAP_SECONDS = 55;

    /**
     * Selectable values for UI dropdowns and tool documentation. A raw cron
     * expression is also accepted but intentionally not listed here.
     */
    public static function options(): array
    {
        return [
            self::AUTO     => 'Automatisch (bei neuen Quelldaten)',
            'every_minute' => 'Jede Minute',
            'every_5_min'  => 'Alle 5 Minuten',
            'every_15_min' => 'Alle 15 Minuten',
            'hourly'       => 'Stündlich',
            'daily'        => 'Täglich (00:00)',
            'weekly'       => 'Wöchentlich (Mo 00:00)',
            'monthly'      => 'Monatlich (1. um 00:00)',
            self::MANUAL   => 'Nur manuell',
        ];
    }

    public static function label(?string $schedule): string
    {
        $schedule = self::normalize($schedule);

        return self::options()[$schedule] ?? $schedule;
    }

    /**
     * Empty/missing falls back to the default so existing rows and sloppy
     * input behave identically.
     */
    public static function normalize(?string $schedule): string
    {
        $schedule = trim((string) $schedule);

        return $schedule === '' ? self::DEFAULT : $schedule;
    }

    /**
     * Accepts the listed options plus any valid cron expression.
     */
    public static function isValid(?string $schedule): bool
    {
        $schedule = self::normalize($schedule);

        if ($schedule === self::AUTO || $schedule === self::MANUAL) {
            return true;
        }

        $cron = ScheduleExpression::toCron($schedule);

        return $cron !== null && CronExpression::isValidExpression($cron);
    }

    /**
     * @param  string|null       $schedule      the KPI's snapshot_schedule
     * @param  CarbonInterface|null $cachedAt   when the KPI was last computed
     * @param  CarbonInterface|null $lastImportAt newest last_run_at across its streams
     * @param  bool              $isDateBound   true when the value depends on the
     *                                          calendar (rolling range like "last_7_days")
     */
    public static function isDue(
        ?string $schedule,
        ?CarbonInterface $cachedAt,
        ?CarbonInterface $lastImportAt,
        bool $isDateBound,
        CarbonInterface $now
    ): bool {
        $schedule = self::normalize($schedule);

        if ($schedule === self::MANUAL) {
            return false;
        }

        // An unknown or malformed expression must not freeze a KPI forever —
        // fall back to auto. Validation on write keeps this rare.
        if ($schedule !== self::AUTO && !self::isValid($schedule)) {
            $schedule = self::AUTO;
        }

        // Never computed yet: always due, whatever the schedule says.
        if ($cachedAt === null) {
            return true;
        }

        // Overlap guard applies to every mode.
        if ($cachedAt->copy()->addSeconds(self::MIN_GAP_SECONDS)->greaterThan($now)) {
            return false;
        }

        if ($schedule === self::AUTO) {
            return self::isDueInAutoMode($cachedAt, $lastImportAt, $isDateBound, $now);
        }

        return (new CronExpression(ScheduleExpression::toCron($schedule)))->isDue($now);
    }

    private static function isDueInAutoMode(
        CarbonInterface $cachedAt,
        ?CarbonInterface $lastImportAt,
        bool $isDateBound,
        CarbonInterface $now
    ): bool {
        // The sources moved since we last looked.
        if ($lastImportAt !== null && !$cachedAt->greaterThan($lastImportAt)) {
            return true;
        }

        // A rolling range ("Letzte 7 Tage", "Laufender Monat") shifts with the
        // clock, not with the data — recompute on the first tick of a new day.
        if ($isDateBound && $cachedAt->lessThan($now->copy()->startOfDay())) {
            return true;
        }

        // Staleness floor.
        return $cachedAt->copy()->addHours(self::MAX_AGE_HOURS)->lessThanOrEqualTo($now);
    }
}
