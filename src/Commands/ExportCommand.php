<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use KirillDakhniuk\DeadDrop\Concerns\FormatsBytes;
use KirillDakhniuk\DeadDrop\Exporter;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

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

        $dateConditions = $this->promptForDateRange();
        $overrides = $dateConditions ? ['where' => $dateConditions] : null;

        $result = spin(
            callback: fn () => $exporter->exportTable($table, $connection, $outputPath, $overrides),
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
        $dateConditions = $this->promptForDateRange();
        $overrides = $dateConditions ? ['where' => $dateConditions] : null;

        $results = spin(
            callback: fn () => $exporter->exportAll($outputPath, $connection, $overrides),
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

    protected function promptForDateRange(): ?array
    {
        $option = select(
            label: 'Filter by date range?',
            options: [
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last_week' => 'Last 7 days',
                'last_month' => 'Last 30 days',
                'custom' => 'Custom interval',
            ],
            default: 'today'
        );

        $this->newLine();

        if ($option === 'custom') {
            return $this->promptForCustomDateRange();
        }

        return $this->getPresetDateRange($option);
    }

    protected function promptForCustomDateRange(): ?array
    {
        $dateFrom = $this->promptForDate('Filter from date (optional)');
        $dateTo = $this->promptForDate('Filter to date (optional)');

        if (! $dateFrom && ! $dateTo) {
            return null;
        }

        $conditions = [];
        if ($dateFrom) {
            $conditions[] = ['created_at', '>=', $dateFrom->toDateTimeString()];
        }
        if ($dateTo) {
            $conditions[] = ['created_at', '<=', $dateTo->toDateTimeString()];
        }

        return $conditions;
    }

    protected function getPresetDateRange(string $option): array
    {
        $conditions = [];

        switch ($option) {
            case 'today':
                $conditions[] = ['created_at', '>=', Carbon::today()->startOfDay()->toDateTimeString()];
                break;

            case 'yesterday':
                $conditions[] = ['created_at', '>=', Carbon::yesterday()->startOfDay()->toDateTimeString()];
                $conditions[] = ['created_at', '<', Carbon::today()->startOfDay()->toDateTimeString()];
                break;

            case 'last_week':
                $conditions[] = ['created_at', '>=', Carbon::now()->subDays(7)->startOfDay()->toDateTimeString()];
                break;

            case 'last_month':
                $conditions[] = ['created_at', '>=', Carbon::now()->subDays(30)->startOfDay()->toDateTimeString()];
                break;
        }

        return $conditions;
    }

    protected function promptForDate(string $label): ?Carbon
    {
        while (true) {
            $input = text(
                label: $label,
                hint: 'Examples: 2024-01-01, last month, 30 days ago, yesterday (or leave empty to skip)'
            );

            if (empty(trim($input))) {
                return null;
            }

            try {
                return Carbon::parse($input);
            } catch (\Exception $e) {
                $this->error('Invalid date format. Please try again.');
            }
        }
    }
}
