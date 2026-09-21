<?php

namespace Platform\Datawarehouse\Services;

/**
 * Shared translation of the module's schedule shortcuts into cron
 * expressions. Used by the stream pull dispatcher and the KPI snapshot
 * scheduler so both speak the same syntax — a schedule string is either
 * one of the shortcuts below or a plain 5-field cron expression.
 */
final class ScheduleExpression
{
    public const SHORTCUTS = [
        'every_minute' => '* * * * *',
        'every_5_min'  => '*/5 * * * *',
        'every_15_min' => '*/15 * * * *',
        'hourly'       => '0 * * * *',
        'daily'        => '0 0 * * *',
        'weekly'       => '0 0 * * 1',
        'monthly'      => '0 0 1 * *',
        'quarterly'    => '0 0 1 1,4,7,10 *',
        'yearly'       => '0 0 1 1 *',
    ];

    /**
     * Resolve a schedule string to a cron expression. Returns null when the
     * string is neither a known shortcut nor something cron-shaped — the
     * caller decides what "unusable" means in its context.
     */
    public static function toCron(string $schedule): ?string
    {
        $schedule = trim($schedule);

        if ($schedule === '') {
            return null;
        }

        return self::SHORTCUTS[$schedule]
            ?? (str_contains($schedule, ' ') ? $schedule : null);
    }
}
