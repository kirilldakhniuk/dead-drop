<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\ServiceProvider;
use KirillDakhniuk\DeadDrop\Commands\ExportCommand;
use KirillDakhniuk\DeadDrop\Commands\ImportCommand;
use KirillDakhniuk\DeadDrop\Commands\StatusCommand;

class DeadDropServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/dead-drop.php',
            'dead-drop'
        );

        $this->app->singleton(Exporter::class);
        $this->app->singleton(Importer::class);

        // Register facade alias
        $this->app->singleton('dead-drop', function ($app) {
            return $app->make(Exporter::class);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportCommand::class,
                ImportCommand::class,
                StatusCommand::class,
            ]);

            // Publish configuration file
            $this->publishes([
                __DIR__.'/../config/dead-drop.php' => config_path('dead-drop.php'),
            ], 'dead-drop-config');

            // Allow publishing with 'config' tag as well
            $this->publishes([
                __DIR__.'/../config/dead-drop.php' => config_path('dead-drop.php'),
            ], 'config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations/create_dead_drop_exports_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_dead_drop_exports_table.php'),
            ], 'dead-drop-migrations');

            // Allow publishing with 'migrations' tag as well
            $this->publishes([
                __DIR__.'/../database/migrations/create_dead_drop_exports_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_dead_drop_exports_table.php'),
            ], 'migrations');
        }
    }
}
