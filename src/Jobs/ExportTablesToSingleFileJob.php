<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use KirillDakhniuk\DeadDrop\Concerns\TracksExportStatus;
use KirillDakhniuk\DeadDrop\Exporter;

class ExportTablesToSingleFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksExportStatus;

    public function __construct(
        public array $tables,
        public string $outputPath,
        public string $databaseConnection,
        public ?array $overrides = null,
        public ?string $exportId = null
    ) {
        $this->onConnection(config('dead-drop.queue.connection'));
        $this->onQueue(config('dead-drop.queue.queue_name', 'default'));
    }

    public function handle(Exporter $exporter): void
    {
        $this->trackStatus(fn () => $exporter->exportTablesToSingleFile(
            $this->tables,
            $this->outputPath,
            $this->databaseConnection,
            $this->overrides,
            fn ($current) => $this->reportProgress($current)
        ));
    }
}
