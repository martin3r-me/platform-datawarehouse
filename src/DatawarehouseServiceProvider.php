<?php

namespace Platform\Datawarehouse;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DatawarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/datawarehouse.php', 'datawarehouse');
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
