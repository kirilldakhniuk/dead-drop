<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Commands;

use Illuminate\Console\Command;
use KirillDakhniuk\DeadDrop\Actions\Export\CountExportRecords;
use KirillDakhniuk\DeadDrop\Actions\Export\ExportTablesToFile;
use KirillDakhniuk\DeadDrop\Actions\Export\ExportTablesToFileAsync;
use KirillDakhniuk\DeadDrop\Concerns\FormatsBytes;
use KirillDakhniuk\DeadDrop\Concerns\ReportsProgress;
use KirillDakhniuk\DeadDrop\ExportPrompter;
use KirillDakhniuk\DeadDrop\ExportRequest;

class ExportCommand extends Command
{
    use FormatsBytes, ReportsProgress;

    protected $signature = 'dead-drop:export
        {--connection= : Database connection to use}
        {--async : Run export in background queue}
        {--no-filter : Skip all prompts and export everything}';

    protected $description = 'Export database tables to a single SQL file';

    public function handle(
        ExportTablesToFile $export,
        ExportTablesToFileAsync $exportAsync,
        CountExportRecords $countRecords,
        ExportPrompter $prompter
    ): int {
        $this->info('Dead Drop - Database Export Tool');
        $this->newLine();

        try {
            $request = $this->buildRequest($prompter);

            return $this->option('async')
                ? $this->executeAsync($exportAsync, $request)
                : $this->executeSync($export, $countRecords, $request);

        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function buildRequest(ExportPrompter $prompter): ExportRequest
    {
        if ($this->option('no-filter')) {
            return ExportRequest::fromConfig($this->getEnabledTables());
        }

        return $prompter->gather($this->option('connection'));
    }

    protected function executeSync(
        ExportTablesToFile $export,
        CountExportRecords $countRecords,
        ExportRequest $request
    ): int {
        $totalRecords = $countRecords->execute($request);

        $this->info('Counting records across '.count($request->tables).' table(s)...');
        $this->newLine();
        $this->info("Exporting {$totalRecords} records to single file...");
        $this->newLine();

        $result = $this->exportWithProgressBar(
            $totalRecords,
            fn ($progressCallback) => $export->execute($request, $progressCallback)
        );

        return $this->displayResult($result);
    }

    protected function executeAsync(ExportTablesToFileAsync $exportAsync, ExportRequest $request): int
    {
        $exportId = $exportAsync->execute($request);

        $this->newLine();
        $this->info('Export queued!');
        $this->info("Track progress with: php artisan dead-drop:status {$exportId}");

        return self::SUCCESS;
    }

    protected function displayResult(array $result): int
    {
        $this->newLine();
        $this->info('Export completed successfully!');
        $this->newLine();

        $rows = [
            ['File', basename($result['file'])],
            ['Path', dirname($result['file'])],
            ['Tables', implode(', ', $result['tables'])],
            ['Total Records', $result['total_records']],
            ['Size', $this->formatBytes($result['size'])],
        ];

        if ($result['cloud_path']) {
            $rows[] = ['Storage', $result['storage_disk']];
            $rows[] = ['Cloud Path', $result['cloud_path']];

            if ($result['local_deleted'] ?? false) {
                $rows[] = ['Local File', 'Deleted after upload'];
            }
        }

        $this->table(['Property', 'Value'], $rows);

        return self::SUCCESS;
    }

    protected function getEnabledTables(): array
    {
        return array_keys(array_filter(
            config('dead-drop.tables', []),
            fn ($config) => $config !== false
        ));
    }
}
