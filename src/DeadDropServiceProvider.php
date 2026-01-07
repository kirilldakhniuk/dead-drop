<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\ServiceProvider;
use KirillDakhniuk\DeadDrop\Commands\ExportCommand;
use KirillDakhniuk\DeadDrop\Commands\ImportCommand;

class DeadDropServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/dead-drop.php',
            'dead-drop'
        );

        $this->app->singleton(Exporter::class, function ($app) {
            return new Exporter;
        });

        $this->app->singleton(Importer::class, function ($app) {
            return new Importer;
        });

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
            ]);

            // Publish configuration file
            $this->publishes([
                __DIR__.'/../config/dead-drop.php' => config_path('dead-drop.php'),
            ], 'dead-drop-config');

            // Allow publishing with 'config' tag as well
            $this->publishes([
                __DIR__.'/../config/dead-drop.php' => config_path('dead-drop.php'),
            ], 'config');
        }
    }
}
