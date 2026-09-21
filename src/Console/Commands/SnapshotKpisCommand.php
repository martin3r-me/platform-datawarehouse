<?php

namespace Platform\Datawarehouse\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\KpiQueryBuilder;
use Platform\Datawarehouse\Services\KpiSnapshotSchedule;

/**
 * Scheduler tick: snapshot every active KPI whose own `snapshot_schedule`
 * says it is due. Runs every minute, but each KPI decides for itself how
 * often that minute actually costs something — see KpiSnapshotSchedule.
 *
 * The gating replaces the former "recompute everything, every minute"
 * behaviour, which cost ~6-10 queries per KPI per minute regardless of
 * whether any source had moved.
 */
class SnapshotKpisCommand extends Command
{
    protected $signature = 'datawarehouse:snapshot-kpis
        {--kpi= : Restrict to one KPI ID}
        {--force : Ignore each KPI\'s snapshot_schedule and recompute now}';

    protected $description = 'Snapshot active KPIs that are due per their snapshot_schedule.';

    public function handle(): int
    {
        $query = DatawarehouseKpi::query()
            ->where('status', 'active')
            ->where('is_group', false)
            ->whereNotNull('definition');

        if ($kpiId = $this->option('kpi')) {
            $query->where('id', $kpiId);
        }

        $kpis = $query->get();
        $lastImports = $this->lastImportPerStream($kpis);

        $builder = new KpiQueryBuilder();
        $now = Carbon::now();
        $force = (bool) $this->option('force');

        $snapshotted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($kpis as $kpi) {
            $due = $force || KpiSnapshotSchedule::isDue(
                $kpi->snapshot_schedule,
                $kpi->cached_at,
                $this->lastImportFor($kpi, $lastImports),
                $kpi->hasRollingDateRange(),
                $now,
            );

            if (!$due) {
                $skipped++;
                continue;
            }

            try {
                $builder->executeAndCache($kpi, 'scheduled');
                $snapshotted++;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("KPI #{$kpi->id} ({$kpi->name}): {$e->getMessage()}");
            }
        }

        $this->info("snapshotted={$snapshotted}, skipped={$skipped}, errors={$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve last_run_at for every stream referenced by any of the KPIs in
     * one query — the auto schedule needs it per KPI, and looking it up per
     * KPI is what made the old tick expensive.
     *
     * @param  iterable<DatawarehouseKpi>  $kpis
     * @return array<int, array{team_id: int, last_run_at: ?Carbon}>
     */
    protected function lastImportPerStream(iterable $kpis): array
    {
        $streamIds = [];

        foreach ($kpis as $kpi) {
            foreach ($kpi->definition['streams'] ?? [] as $stream) {
                if (isset($stream['stream_id'])) {
                    $streamIds[(int) $stream['stream_id']] = true;
                }
            }
        }

        if (empty($streamIds)) {
            return [];
        }

        return DatawarehouseStream::query()
            ->whereIn('id', array_keys($streamIds))
            ->get(['id', 'team_id', 'last_run_at'])
            ->keyBy('id')
            ->map(fn ($stream) => [
                'team_id'     => (int) $stream->team_id,
                'last_run_at' => $stream->last_run_at,
            ])
            ->all();
    }

    /**
     * Newest import across the KPI's own streams. Streams of another team are
     * ignored, mirroring DatawarehouseKpi::isCacheValid().
     *
     * @param  array<int, array{team_id: int, last_run_at: ?Carbon}>  $lastImports
     */
    protected function lastImportFor(DatawarehouseKpi $kpi, array $lastImports): ?Carbon
    {
        $newest = null;

        foreach ($kpi->definition['streams'] ?? [] as $stream) {
            $entry = $lastImports[(int) ($stream['stream_id'] ?? 0)] ?? null;

            if (!$entry || $entry['team_id'] !== (int) $kpi->team_id || !$entry['last_run_at']) {
                continue;
            }

            if ($newest === null || $entry['last_run_at']->greaterThan($newest)) {
                $newest = $entry['last_run_at'];
            }
        }

        return $newest;
    }
}
