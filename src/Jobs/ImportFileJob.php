<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use KirillDakhniuk\DeadDrop\Concerns\TracksExportStatus;
use KirillDakhniuk\DeadDrop\Importer;

class ImportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksExportStatus;

    public function __construct(
        public string $filePath,
        public ?string $databaseConnection = null,
        public ?string $importId = null
    ) {
        $this->onConnection(config('dead-drop.queue.connection'));
        $this->onQueue(config('dead-drop.queue.queue_name', 'default'));
    }

    public function handle(Importer $importer): void
    {
        $this->trackStatus(fn () => $importer->importFromFile($this->filePath, $this->databaseConnection));
    }
}
