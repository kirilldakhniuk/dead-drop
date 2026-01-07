<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\Concerns\FormatsBytes;
use KirillDakhniuk\DeadDrop\Importer;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class ImportCommand extends Command
{
    use FormatsBytes;

    protected $signature = 'dead-drop:import {--connection= : Database connection to use}';

    protected $description = 'Import SQL files into the database';

    public function handle(Importer $importer): int
    {
        $this->info('Dead Drop - Database Import Tool');
        $this->newLine();

        warning('WARNING: Importing will execute SQL statements in your database!');
        $this->newLine();

        if (! confirm('Do you want to continue?', false)) {
            $this->info('Import cancelled.');

            return self::SUCCESS;
        }

        $storageDisk = config('dead-drop.storage.disk', 'local');
        $importFrom = $storageDisk !== 'local'
            ? select(
                label: 'Import from',
                options: ['local' => 'Local file', 'cloud' => "Cloud storage ({$storageDisk})"],
            )
            : 'local';

        $connection = $this->option('connection') ?: config('database.default');

        try {
            if ($importFrom === 'cloud') {
                return $this->importFromCloud($importer, $connection);
            }

            return $this->importFromLocal($importer, $connection);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function importFromLocal(Importer $importer, string $connection): int
    {
        $defaultPath = config('dead-drop.output_path', storage_path('app/dead-drop'));

        // List available SQL files
        $sqlFiles = File::glob($defaultPath.'/*.sql');

        if (empty($sqlFiles)) {
            $this->warn("No SQL files found in: {$defaultPath}");

            $customPath = text(
                label: 'Enter the full path to the SQL file',
                required: true,
            );

            if (! File::exists($customPath)) {
                $this->error('File not found: '.$customPath);

                return self::FAILURE;
            }

            $filePath = $customPath;
        } else {
            $options = [];
            foreach ($sqlFiles as $file) {
                $options[$file] = basename($file).' ('.$this->formatBytes(File::size($file)).')';
            }
            $options['custom'] = 'Enter custom path...';

            $selected = select(
                label: 'Select SQL file to import',
                options: $options,
            );

            if ($selected === 'custom') {
                $customPath = text(
                    label: 'Enter the full path to the SQL file',
                    required: true,
                );

                if (! File::exists($customPath)) {
                    $this->error('File not found: '.$customPath);

                    return self::FAILURE;
                }

                $filePath = $customPath;
            } else {
                $filePath = $selected;
            }
        }

        $this->newLine();
        $this->info('Importing: '.basename($filePath));
        $this->info('Size: '.$this->formatBytes(File::size($filePath)));
        $this->newLine();

        if (! confirm('Are you sure you want to import this file?', false)) {
            $this->info('Import cancelled.');

            return self::SUCCESS;
        }

        $result = spin(
            callback: fn () => $importer->importFromFile($filePath, $connection),
            message: 'Importing SQL file...'
        );

        $this->displayResults($result);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function importFromCloud(Importer $importer, string $connection): int
    {
        $storageDisk = config('dead-drop.storage.disk');
        $storagePath = config('dead-drop.storage.path', 'dead-drop');

        $cloudPath = text(
            label: 'Enter the cloud storage path (e.g., dead-drop/users.sql)',
            default: $storagePath.'/',
            required: true,
        );

        $this->newLine();
        $this->info('Importing from cloud: '.$cloudPath);
        $this->info('Storage disk: '.$storageDisk);
        $this->newLine();

        if (! confirm('Are you sure you want to import this file?', false)) {
            $this->info('Import cancelled.');

            return self::SUCCESS;
        }

        $result = spin(
            callback: fn () => $importer->importFromCloud($cloudPath, $storageDisk, $connection),
            message: 'Importing from cloud storage...'
        );

        $this->displayResults($result);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function displayResults(array $result): void
    {
        $this->newLine();

        if ($result['failed'] === 0) {
            $this->info('✓ Import completed successfully!');
        } else {
            $this->warn('⚠ Import completed with errors');
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
                $errorData[] = [
                    $error['statement'],
                    $error['error'],
                ];
            }

            $this->table(['Statement', 'Error'], $errorData);
        }
    }
}
