<?php

namespace Platform\Datawarehouse\Console\Commands;

use Illuminate\Console\Command;
use Platform\Datawarehouse\Services\DateDimensionService;

class SeedDimDateCommand extends Command
{
    protected $signature = 'datawarehouse:seed-dim-date
        {--from=2020-01-01 : Start date}
        {--to=2035-12-31 : End date}';

    protected $description = 'Seed the dw_dim_date calendar dimension table';

    public function handle(DateDimensionService $service): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info("Seeding dw_dim_date from {$from} to {$to}...");

        $count = $service->seed($from, $to);

        $this->info("Done. {$count} date rows upserted.");

        return self::SUCCESS;
    }
}
