<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\Actions\Export\BuildExportQuery;
use KirillDakhniuk\DeadDrop\Actions\Export\WriteTableToHandle;
use KirillDakhniuk\DeadDrop\Actions\Storage\UploadToCloudStorage;

/**
 * @internal
 */
final class Exporter
{
    public function __construct(
        protected BuildExportQuery $queryBuilder,
        protected WriteTableToHandle $tableWriter,
        protected UploadToCloudStorage $cloudUploader
    ) {}

    public function exportTable(
        string $table,
        string $connection,
        string $outputPath,
        ?array $overrides = null,
        ?callable $progressCallback = null
    ): array {
        $config = $this->queryBuilder->resolveTableConfig($table, $overrides);
        $query = $this->queryBuilder->execute($table, $connection, $overrides);

        File::ensureDirectoryExists($outputPath);

        $exportResult = $this->exportToSql($table, $query, $outputPath, $config, $connection, $progressCallback);

        $result = [
            'table' => $table,
            'records' => $exportResult['records'],
            'file' => $exportResult['filename'],
            'size' => File::size($exportResult['filename']),
            'cloud_path' => null,
            'storage_disk' => null,
        ];

        return $this->handleCloudUpload($result, $exportResult['filename']);
    }

    public function exportAll(string $outputPath, ?string $connection = null, ?array $overrides = null): array
    {
        $connection = $connection ?? config('database.default');

        return array_map(
            fn ($table) => $this->exportTable($table, $connection, $outputPath, $overrides),
            array_keys($this->getEnabledTables())
        );
    }

    public function exportAllToSingleFile(
        string $outputPath,
        ?string $connection = null,
        ?array $overrides = null,
        ?callable $progressCallback = null
    ): array {
        $tables = array_keys($this->getEnabledTables());

        if (empty($tables)) {
            throw new \InvalidArgumentException('No tables configured for export');
        }

        return $this->exportTablesToSingleFile($tables, $outputPath, $connection, $overrides, $progressCallback);
    }

    public function exportTablesToSingleFile(
        array $tables,
        string $outputPath,
        ?string $connection = null,
        ?array $overrides = null,
        ?callable $progressCallback = null
    ): array {
        if (empty($tables)) {
            throw new \InvalidArgumentException('No tables specified for export');
        }

        $connection = $connection ?? config('database.default');

        File::ensureDirectoryExists($outputPath);

        $timestamp = now()->format('Y-m-d-His');
        $filename = "{$outputPath}/database-export-{$timestamp}.sql";

        $handle = fopen($filename, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Could not open file for writing: {$filename}");
        }

        try {
            $this->writeFileHeader($handle, $connection, count($tables));

            $driver = config("database.connections.{$connection}.driver");
            $totalRecords = 0;

            foreach ($tables as $table) {
                $config = $this->queryBuilder->resolveTableConfig($table, $overrides);
                $query = $this->queryBuilder->execute($table, $connection, $overrides);
                $primaryKey = $config['primary_key'] ?? 'id';

                $this->writeTableHeader($handle, $table, $config);

                $cumulativeOffset = $totalRecords;
                $recordsExported = $this->tableWriter->execute(
                    $table,
                    $query,
                    $handle,
                    $config,
                    $driver,
                    $connection,
                    $progressCallback ? fn ($current) => $progressCallback($cumulativeOffset + $current) : null,
                    $primaryKey
                );

                $totalRecords += $recordsExported;
            }

            $this->writeFileFooter($handle, $totalRecords);
            fclose($handle);

            $result = [
                'file' => $filename,
                'tables' => $tables,
                'total_records' => $totalRecords,
                'size' => File::size($filename),
                'cloud_path' => null,
                'storage_disk' => null,
            ];

            return $this->handleCloudUpload($result, $filename);

        } catch (\Exception $e) {
            if (isset($handle) && $handle !== false) {
                fclose($handle);
            }
            if (File::exists($filename)) {
                File::delete($filename);
            }

            throw $e;
        }
    }

    public function export(
        string $table,
        ?array $overrides = null,
        ?string $connection = null,
        ?string $outputPath = null
    ): array {
        $connection = $connection ?? config('database.default');
        $outputPath = $outputPath ?? config('dead-drop.output_path', storage_path('app/dead-drop'));

        return $this->exportTable($table, $connection, $outputPath, $overrides);
    }

    public function countRecords(string $table, string $connection, ?array $overrides = null): int
    {
        $config = $this->queryBuilder->resolveTableConfig($table, $overrides);
        $query = $this->queryBuilder->execute($table, $connection, $overrides);

        $count = $query->count();

        return isset($config['limit']) ? min($config['limit'], $count) : $count;
    }

    public function countAllRecords(string $connection, ?array $overrides = null): int
    {
        return array_sum(array_map(
            fn ($table) => $this->countRecords($table, $connection, $overrides),
            array_keys($this->getEnabledTables())
        ));
    }

    public function exportAsync(
        string $table,
        ?array $overrides = null,
        ?string $connection = null,
        ?string $outputPath = null
    ): string {
        $connection = $connection ?? config('database.default');
        $outputPath = $outputPath ?? config('dead-drop.output_path', storage_path('app/dead-drop'));

        $exportId = \Illuminate\Support\Str::uuid()->toString();

        ExportStatus::create($exportId, 'export', [
            'table' => $table,
            'connection' => $connection,
        ]);

        Jobs\ExportTableJob::dispatch($table, $connection, $outputPath, $overrides, $exportId);

        return $exportId;
    }

    public function exportAllAsync(?string $outputPath = null, ?string $connection = null, ?array $overrides = null): array
    {
        $connection = $connection ?? config('database.default');
        $outputPath = $outputPath ?? config('dead-drop.output_path', storage_path('app/dead-drop'));

        $exportIds = [];
        foreach (array_keys($this->getEnabledTables()) as $table) {
            $exportIds[$table] = $this->exportAsync($table, $overrides, $connection, $outputPath);
        }

        return $exportIds;
    }

    public function exportAllToSingleFileAsync(
        ?string $outputPath = null,
        ?string $connection = null,
        ?array $overrides = null
    ): string {
        $tables = array_keys($this->getEnabledTables());

        if (empty($tables)) {
            throw new \InvalidArgumentException('No tables configured for export');
        }

        return $this->exportTablesToSingleFileAsync($tables, $outputPath, $connection, $overrides);
    }

    public function exportTablesToSingleFileAsync(
        array $tables,
        ?string $outputPath = null,
        ?string $connection = null,
        ?array $overrides = null
    ): string {
        if (empty($tables)) {
            throw new \InvalidArgumentException('No tables specified for export');
        }

        $connection = $connection ?? config('database.default');
        $outputPath = $outputPath ?? config('dead-drop.output_path', storage_path('app/dead-drop'));

        $exportId = \Illuminate\Support\Str::uuid()->toString();

        ExportStatus::create($exportId, 'export', [
            'type' => 'single-file',
            'tables' => $tables,
            'connection' => $connection,
        ]);

        Jobs\ExportTablesToSingleFileJob::dispatch($tables, $outputPath, $connection, $overrides, $exportId);

        return $exportId;
    }

    protected function getEnabledTables(): array
    {
        return array_filter(
            config('dead-drop.tables', []),
            fn ($config) => $config !== false
        );
    }

    protected function exportToSql(
        string $table,
        Builder $query,
        string $outputPath,
        array $config,
        string $connection,
        ?callable $progressCallback
    ): array {
        $filename = "{$outputPath}/{$table}.sql";
        $handle = fopen($filename, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Could not open file for writing: {$filename}");
        }

        fwrite($handle, "-- Table: {$table}\n");
        fwrite($handle, '-- Exported: '.now()->toDateTimeString()."\n");
        fwrite($handle, "-- Format: upsert (safe for re-import)\n\n");

        $driver = config("database.connections.{$connection}.driver");
        $primaryKey = $config['primary_key'] ?? 'id';

        $recordCount = $this->tableWriter->execute($table, $query, $handle, $config, $driver, $connection, $progressCallback, $primaryKey);

        fclose($handle);

        return [
            'filename' => $filename,
            'records' => $recordCount,
        ];
    }

    protected function handleCloudUpload(array $result, string $localFilePath): array
    {
        $cloudResult = $this->cloudUploader->execute($localFilePath);

        if ($cloudResult) {
            $result['cloud_path'] = $cloudResult['cloud_path'];
            $result['storage_disk'] = $cloudResult['storage_disk'];
            $result['local_deleted'] = $cloudResult['local_deleted'];
        }

        return $result;
    }

    protected function writeFileHeader(mixed $handle, string $connection, int $tableCount): void
    {
        fwrite($handle, "-- Dead Drop Export\n");
        fwrite($handle, '-- Exported: '.now()->toDateTimeString()."\n");
        fwrite($handle, "-- Connection: {$connection}\n");
        fwrite($handle, "-- Tables: {$tableCount}\n\n");
    }

    protected function writeTableHeader(mixed $handle, string $table, array $config): void
    {
        fwrite($handle, "\n-- Table: {$table}\n");

        if (isset($config['where'])) {
            $filters = array_map(fn ($c) => implode(' ', $c), $config['where']);
            fwrite($handle, '-- Filters: '.implode(', ', $filters)."\n");
        }

        fwrite($handle, "\n");
    }

    protected function writeFileFooter(mixed $handle, int $totalRecords): void
    {
        fwrite($handle, "\n-- Export complete: {$totalRecords} records\n");
    }
}
