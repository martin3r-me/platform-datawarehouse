<?php

namespace Platform\Datawarehouse\Console\Commands;

use Illuminate\Console\Command;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Services\KpiQueryBuilder;

/**
 * Scheduler tick: iterate over all active KPIs and snapshot their
 * current value. Runs every minute — always creates a snapshot,
 * even when the value hasn't changed, so trends have a continuous
 * time series.
 *
 * Each KPI's effective refresh rate is bounded by the slowest
 * underlying stream (the "weakest link"), but the snapshot is still
 * recorded so the time series stays gapless.
 */
class SnapshotKpisCommand extends Command
{
    protected $signature = 'datawarehouse:snapshot-kpis {--kpi= : Restrict to one KPI ID}';

    protected $description = 'Snapshot all active KPIs (runs every minute for continuous time series).';

    public function handle(): int
    {
        $query = DatawarehouseKpi::query()
            ->where('status', 'active')
            ->whereNotNull('definition');

        if ($kpiId = $this->option('kpi')) {
            $query->where('id', $kpiId);
        }

        $builder = new KpiQueryBuilder();
        $snapshotted = 0;
        $errors = 0;

        foreach ($query->cursor() as $kpi) {
            try {
                $builder->executeAndCache($kpi, 'scheduled');
                $snapshotted++;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("KPI #{$kpi->id} ({$kpi->name}): {$e->getMessage()}");
            }
        }

        $this->info("snapshotted={$snapshotted}, errors={$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
