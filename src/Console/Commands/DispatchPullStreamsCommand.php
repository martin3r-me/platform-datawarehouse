<?php

namespace Platform\Datawarehouse\Console\Commands;

use Cron\CronExpression;
use Illuminate\Console\Command;
use Platform\Datawarehouse\Jobs\PullStreamJob;
use Platform\Datawarehouse\Models\DatawarehouseStream;

/**
 * Scheduler tick: iterate over all active pull streams and dispatch a
 * PullStreamJob for each one whose `pull_schedule` is due.
 *
 * Supported schedule syntax:
 *   - standard 5-field cron expressions ("0 * * * *", "*\/15 * * * *")
 *   - shortcuts: "hourly", "daily", "every_15_min", "every_5_min", "every_minute"
 */
class DispatchPullStreamsCommand extends Command
{
    protected $signature = 'datawarehouse:dispatch-pulls {--stream= : Restrict to one stream ID}';

    protected $description = 'Dispatch pull jobs for streams whose schedule is due.';

    public function handle(): int
    {
        $query = DatawarehouseStream::query()
            ->where('source_type', 'pull_get')
            ->where('status', 'active')
            ->whereNotNull('connection_id')
            ->whereNotNull('endpoint_key');

        if ($streamId = $this->option('stream')) {
            $query->where('id', $streamId);
        }

        $now = now();
        $dispatched = 0;
        $skipped = 0;

        foreach ($query->cursor() as $stream) {
            if (!$this->isDue($stream, $now)) {
                $skipped++;
                continue;
            }
            PullStreamJob::dispatch($stream->id);
            $dispatched++;
        }

        $this->info("dispatched={$dispatched}, skipped={$skipped}");
        return self::SUCCESS;
    }

    protected function isDue(DatawarehouseStream $stream, \DateTimeInterface $now): bool
    {
        $schedule = trim((string) $stream->pull_schedule);
        if ($schedule === '') {
            return false;
        }

        $cron = $this->toCron($schedule);
        if ($cron === null) {
            return false;
        }

        // Run when the minute boundary matches.
        // Last run check avoids double-dispatch on overlapping scheduler ticks.
        if (!CronExpression::isValidExpression($cron)) {
            return false;
        }

        $expr = new CronExpression($cron);
        if (!$expr->isDue($now)) {
            return false;
        }

        // Gate: never trigger more than once per minute per stream.
        if ($stream->last_pull_at && $stream->last_pull_at->diffInSeconds($now) < 55) {
            return false;
        }

        return true;
    }

    protected function toCron(string $schedule): ?string
    {
        return match ($schedule) {
            'every_minute'  => '* * * * *',
            'every_5_min'   => '*/5 * * * *',
            'every_15_min'  => '*/15 * * * *',
            'hourly'        => '0 * * * *',
            'daily'         => '0 0 * * *',
            'weekly'        => '0 0 * * 1',
            'monthly'       => '0 0 1 * *',
            'quarterly'     => '0 0 1 1,4,7,10 *',
            'yearly'        => '0 0 1 1 *',
            default         => str_contains($schedule, ' ') ? $schedule : null,
        };
    }
}
