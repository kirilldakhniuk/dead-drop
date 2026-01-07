<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Commands;

use Illuminate\Console\Command;
use KirillDakhniuk\DeadDrop\Concerns\FormatsBytes;
use KirillDakhniuk\DeadDrop\Exporter;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class ExportCommand extends Command
{
    use FormatsBytes;

    protected $signature = 'dead-drop:export {--connection= : Database connection to use}';

    protected $description = 'Export database tables to SQL';

    public function handle(Exporter $exporter): int
    {
        $this->info('Dead Drop - Database Export Tool');
        $this->newLine();

        $exportAll = confirm(
            label: 'Export all configured tables?',
            default: false,
            hint: 'Select "No" to export a specific table'
        );

        $connection = $this->option('connection') ?: config('database.default');
        $outputPath = config('dead-drop.output_path', storage_path('app/dead-drop'));

        try {
            if ($exportAll) {
                return $this->exportAllTables($exporter, $outputPath, $connection);
            }

            return $this->exportSingleTable($exporter, $connection, $outputPath);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function exportSingleTable(
        Exporter $exporter,
        string $connection,
        string $outputPath
    ): int {
        $tables = config('dead-drop.tables', []);
        $availableTables = array_keys(array_filter($tables, fn ($config) => $config !== false));

        if (empty($availableTables)) {
            $this->error('No tables configured for export');

            return self::FAILURE;
        }

        $table = select(
            label: 'Which table do you want to export?',
            options: $availableTables,
        );

        $result = spin(
            callback: fn () => $exporter->exportTable($table, $connection, $outputPath),
            message: "Exporting {$table}..."
        );

        $this->newLine();
        $this->info('✓ Export completed successfully!');
        $this->newLine();

        $tableData = [
            ['Table', $result['table']],
            ['Records', $result['records']],
            ['File', $result['file']],
            ['Size', $this->formatBytes($result['size'])],
        ];

        if ($result['cloud_path']) {
            $tableData[] = ['Storage', $result['storage_disk']];
            $tableData[] = ['Cloud Path', $result['cloud_path']];

            if (isset($result['local_deleted']) && $result['local_deleted']) {
                $tableData[] = ['Local File', 'Deleted after upload'];
            }
        }

        $this->table(['Property', 'Value'], $tableData);

        return self::SUCCESS;
    }

    protected function exportAllTables(
        Exporter $exporter,
        string $outputPath,
        string $connection
    ): int {
        $results = spin(
            callback: fn () => $exporter->exportAll($outputPath, $connection),
            message: 'Exporting all configured tables...'
        );

        if (empty($results)) {
            $this->warn('No tables configured for export');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('✓ Export completed successfully!');
        $this->newLine();

        $tableData = [];
        $totalRecords = 0;
        $totalSize = 0;
        $cloudStorage = null;

        foreach ($results as $result) {
            $row = [
                $result['table'],
                $result['records'],
                basename($result['file']),
                $this->formatBytes($result['size']),
            ];

            if ($result['cloud_path']) {
                $row[] = $result['cloud_path'];
                $cloudStorage = $result['storage_disk'];
            } elseif ($cloudStorage) {
                $row[] = '-';
            }

            $tableData[] = $row;
            $totalRecords += $result['records'];
            $totalSize += $result['size'];
        }

        $headers = ['Table', 'Records', 'File', 'Size'];
        if ($cloudStorage) {
            $headers[] = 'Cloud Path';
        }

        $this->table($headers, $tableData);

        $this->newLine();
        $this->info("Total: {$totalRecords} records exported across ".count($results).' tables');
        $this->info('Total size: '.$this->formatBytes($totalSize));

        if ($cloudStorage) {
            $this->info("Storage: {$cloudStorage}");
        }

        $this->info("Output directory: {$outputPath}");

        return self::SUCCESS;
    }
}
