<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * @internal
 */
final class Importer
{
    public function importFromFile(string $filePath, ?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        if (! File::exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = File::get($filePath);

        return $this->importFromSql($content, $connection, $filePath);
    }

    public function importFromCloud(string $cloudPath, string $disk, ?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        $storage = Storage::disk($disk);

        if (! $storage->exists($cloudPath)) {
            throw new \InvalidArgumentException("File not found in cloud storage: {$cloudPath}");
        }

        $content = $storage->get($cloudPath);

        return $this->importFromSql($content, $connection, $cloudPath);
    }

    protected function importFromSql(string $sql, string $connection, string $source): array
    {
        // Remove comments and empty lines
        $sql = $this->cleanSql($sql);

        // Split into individual statements
        $statements = $this->splitStatements($sql);

        $executed = 0;
        $failed = 0;
        $errors = [];

        DB::connection($connection)->beginTransaction();

        try {
            foreach ($statements as $statement) {
                if (trim($statement) === '') {
                    continue;
                }

                try {
                    DB::connection($connection)->statement($statement);
                    $executed++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'statement' => substr($statement, 0, 100).'...',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::connection($connection)->commit();
        } catch (\Exception $e) {
            DB::connection($connection)->rollBack();
            throw new \RuntimeException("Import failed: {$e->getMessage()}");
        }

        return [
            'source' => $source,
            'executed' => $executed,
            'failed' => $failed,
            'total' => count($statements),
            'errors' => $errors,
        ];
    }

    protected function cleanSql(string $sql): string
    {
        // Remove SQL comments
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Remove empty lines
        $sql = preg_replace('/^\s*[\r\n]/m', '', $sql);

        return trim($sql);
    }

    protected function splitStatements(string $sql): array
    {
        // Split by semicolon but not within quotes
        $statements = [];
        $currentStatement = '';
        $inQuotes = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $sql[$i - 1] !== '\\')) {
                if (! $inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
            }

            if ($char === ';' && ! $inQuotes) {
                $statements[] = trim($currentStatement);
                $currentStatement = '';
            } else {
                $currentStatement .= $char;
            }
        }

        // Add the last statement if there's any
        if (trim($currentStatement) !== '') {
            $statements[] = trim($currentStatement);
        }

        return array_filter($statements);
    }
}
