<?php

namespace Platform\Datawarehouse;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Datawarehouse\Console\Commands\DispatchPullStreamsCommand;
use Platform\Datawarehouse\Console\Commands\SeedDimDateCommand;
use Platform\Datawarehouse\Console\Commands\SnapshotKpisCommand;
use Platform\Datawarehouse\Providers\Bundesland\BundeslandProvider;
use Platform\Datawarehouse\Providers\Feiertage\FeiertageProvider;
use Platform\Datawarehouse\Providers\Land\LandProvider;
use Platform\Datawarehouse\Providers\Landkreis\LandkreisProvider;
use Platform\Datawarehouse\Providers\Lexoffice\LexofficeProvider;
use Platform\Datawarehouse\Providers\OpenMeteo\OpenMeteoProvider;
use Platform\Datawarehouse\Providers\ProviderRegistry;
use Platform\Datawarehouse\Providers\RkiCovidInzidenz\RkiCovidInzidenzProvider;
use Platform\Datawarehouse\Providers\SchulferienNrw\SchulferienNrwProvider;
use Platform\Datawarehouse\Providers\Sprache\SpracheProvider;
use Platform\Datawarehouse\Providers\Waehrung\WaehrungProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DatawarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/datawarehouse.php', 'datawarehouse');

        // ProviderRegistry is a singleton so all providers register into the same instance.
        $this->app->singleton(ProviderRegistry::class);
    }

    public function boot(): void
    {
        if (
            config()->has('datawarehouse.routing') &&
            config()->has('datawarehouse.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'datawarehouse',
                'title'      => 'Datawarehouse',
                'routing'    => config('datawarehouse.routing'),
                'guard'      => config('datawarehouse.guard'),
                'navigation' => config('datawarehouse.navigation'),
                'sidebar'    => config('datawarehouse.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('datawarehouse')) {
            ModuleRouter::group('datawarehouse', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            // No auth required - ingest endpoint uses token-based auth
            ModuleRouter::apiGroup('datawarehouse', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            }, requireAuth: false);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/datawarehouse.php' => config_path('datawarehouse.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'datawarehouse');

        $this->registerLivewireComponents();
        $this->registerPullProviders();
        $this->registerTools();

        // Error Reporter Registration
        try {
            resolve(\Platform\Core\Services\ErrorReporterRegistry::class)
                ->register('datawarehouse', 'Platform\\Datawarehouse');
        } catch (\Throwable $e) {}

        $this->ensureDimDateSeeded();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchPullStreamsCommand::class,
                SeedDimDateCommand::class,
                SnapshotKpisCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                // Run the pull dispatcher every minute; it handles per-stream schedule gating.
                $schedule->command('datawarehouse:dispatch-pulls')
                    ->everyMinute()
                    ->withoutOverlapping(5)
                    ->runInBackground();

                // Snapshot all active KPIs every minute for continuous time series.
                $schedule->command('datawarehouse:snapshot-kpis')
                    ->everyMinute()
                    ->withoutOverlapping(10)
                    ->runInBackground();
            });
        }
    }

    /**
     * Registriert Datawarehouse-Tools für die AI/Chat-Funktionalität.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Overview (immer zuerst — Einstiegspunkt für LLMs ins Modul)
            $registry->register(new \Platform\Datawarehouse\Tools\DwhOverviewTool());

            // Streams (CRUD + Status-Actions)
            $registry->register(new \Platform\Datawarehouse\Tools\ListStreamsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\UpdateStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\ActivateStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\PauseStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\ResumeStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\ArchiveStreamTool());
            $registry->register(new \Platform\Datawarehouse\Tools\PreviewStreamDataTool());
            $registry->register(new \Platform\Datawarehouse\Tools\IngestStreamTool());

            // Stream Columns (CRUD + Bulk)
            $registry->register(new \Platform\Datawarehouse\Tools\ListStreamColumnsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateStreamColumnTool());
            $registry->register(new \Platform\Datawarehouse\Tools\UpdateStreamColumnTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteStreamColumnTool());
            $registry->register(new \Platform\Datawarehouse\Tools\BulkCreateStreamColumnsTool());

            // Stream Relations (Multi-Stream-JOINs für KPIs)
            $registry->register(new \Platform\Datawarehouse\Tools\ListStreamRelationsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateStreamRelationTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteStreamRelationTool());

            // Stream Exclusions (bereinigte KPI-Basis, konfigurierbar)
            $registry->register(new \Platform\Datawarehouse\Tools\GetStreamExclusionsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\SetStreamExclusionsTool());

            // Connections (CRUD + Test)
            $registry->register(new \Platform\Datawarehouse\Tools\ListConnectionsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetConnectionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateConnectionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\UpdateConnectionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteConnectionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\TestConnectionTool());

            // Providers (read-only)
            $registry->register(new \Platform\Datawarehouse\Tools\ListProvidersTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetProviderTool());

            // Provider-Definitionen (konfigurierbare HTTP-Provider — CRUD + Test)
            $registry->register(new \Platform\Datawarehouse\Tools\ListProviderDefinitionsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetProviderDefinitionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateProviderDefinitionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\UpdateProviderDefinitionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteProviderDefinitionTool());
            $registry->register(new \Platform\Datawarehouse\Tools\TestProviderDefinitionTool());

            // KPIs (CRUD + Execute)
            $registry->register(new \Platform\Datawarehouse\Tools\ListKpisTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetKpiTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateKpiTool());
            $registry->register(new \Platform\Datawarehouse\Tools\UpdateKpiTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteKpiTool());
            $registry->register(new \Platform\Datawarehouse\Tools\ExecuteKpiTool());
            $registry->register(new \Platform\Datawarehouse\Tools\ExecuteKpiAllRangesTool());
            $registry->register(new \Platform\Datawarehouse\Tools\PreviewKpiTool());

            // Dashboards (CRUD + Pivot-Operations)
            $registry->register(new \Platform\Datawarehouse\Tools\ListDashboardsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetDashboardTool());
            $registry->register(new \Platform\Datawarehouse\Tools\CreateDashboardTool());
            $registry->register(new \Platform\Datawarehouse\Tools\UpdateDashboardTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DeleteDashboardTool());
            $registry->register(new \Platform\Datawarehouse\Tools\AttachKpiToDashboardTool());
            $registry->register(new \Platform\Datawarehouse\Tools\DetachKpiFromDashboardTool());
            $registry->register(new \Platform\Datawarehouse\Tools\ReorderDashboardKpisTool());

            // Imports (read-only)
            $registry->register(new \Platform\Datawarehouse\Tools\ListImportsTool());
            $registry->register(new \Platform\Datawarehouse\Tools\GetImportTool());
        } catch (\Throwable $e) {
            \Log::warning('Datawarehouse: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    protected function registerPullProviders(): void
    {
        /** @var ProviderRegistry $registry */
        $registry = $this->app->make(ProviderRegistry::class);
        $registry->register(new LexofficeProvider());
        $registry->register(new FeiertageProvider());
        $registry->register(new LandProvider());
        $registry->register(new SchulferienNrwProvider());
        $registry->register(new BundeslandProvider());
        $registry->register(new LandkreisProvider());
        $registry->register(new WaehrungProvider());
        $registry->register(new SpracheProvider());
        $registry->register(new OpenMeteoProvider());
        $registry->register(new RkiCovidInzidenzProvider());
    }

    protected function ensureDimDateSeeded(): void
    {
        try {
            if (!Schema::hasTable('dw_dim_date')) {
                return;
            }

            if (\Illuminate\Support\Facades\DB::table('dw_dim_date')->exists()) {
                return;
            }

            $service = new \Platform\Datawarehouse\Services\DateDimensionService();
            $service->seed();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Datawarehouse: dim_date auto-seed failed: ' . $e->getMessage());
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Datawarehouse\\Livewire';
        $prefix = 'datawarehouse';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
