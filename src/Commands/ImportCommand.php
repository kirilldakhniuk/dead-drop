<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Commands;

use Illuminate\Console\Command;
use KirillDakhniuk\DeadDrop\Actions\Import\ImportFromFile;
use KirillDakhniuk\DeadDrop\Actions\Import\ImportFromFileAsync;
use KirillDakhniuk\DeadDrop\ImportPrompter;
use KirillDakhniuk\DeadDrop\ImportRequest;

use function Laravel\Prompts\spin;

class ImportCommand extends Command
{
    protected $signature = 'dead-drop:import
        {--connection= : Database connection to use}
        {--async : Run import in background queue}';

    protected $description = 'Import SQL files into the database';

    public function handle(
        ImportFromFile $import,
        ImportFromFileAsync $importAsync,
        ImportPrompter $prompter
    ): int {
        $this->info('Dead Drop - Database Import Tool');
        $this->newLine();

        try {
            $request = $prompter->gather($this->option('connection'));

            if (! $request) {
                $this->info('Import cancelled.');

                return self::SUCCESS;
            }

            return $this->option('async')
                ? $this->executeAsync($importAsync, $request)
                : $this->executeSync($import, $request);

        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function executeSync(ImportFromFile $import, ImportRequest $request): int
    {
        $result = spin(
            callback: fn () => $import->execute($request),
            message: 'Importing SQL file...'
        );

        return $this->displayResult($result);
    }

    protected function executeAsync(ImportFromFileAsync $importAsync, ImportRequest $request): int
    {
        $importId = $importAsync->execute($request);

        $this->newLine();
        $this->info('Import queued!');
        $this->info("Track progress with: php artisan dead-drop:status {$importId}");

        return self::SUCCESS;
    }

    protected function displayResult(array $result): int
    {
        $this->newLine();

        if ($result['failed'] === 0) {
            $this->info('Import completed successfully!');
        } else {
            $this->warn('Import completed with errors');
        }

        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Source', basename($result['source'])],
                ['Total Statements', $result['total']],
                ['Executed', $result['executed']],
                ['Failed', $result['failed']],
            ]
        );

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->error('Errors:');
            $this->newLine();

            $errorData = [];
            foreach ($result['errors'] as $error) {
                $errorData[] = [$error['statement'], $error['error']];
            }

            $this->table(['Statement', 'Error'], $errorData);
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
