<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use Illuminate\Support\Facades\DB;

class GenerateUpsertSql
{
    protected ?string $connection = null;

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
    }

    public function setConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function forRow(string $table, array $row, string $driver): string
    {
        $columns = implode(', ', array_map(fn ($col) => $this->quoteIdentifier($col, $driver), array_keys($row)));
        $values = $this->formatValues(array_values($row), $driver);

        return $this->buildStatement($table, $row, $columns, "({$values})", $driver);
    }

    public function forBatch(string $table, array $rows, string $driver): string
    {
        if (empty($rows)) {
            return '';
        }

        $batchSize = config('dead-drop.performance.batch_size', 100);
        $batches = array_chunk($rows, $batchSize);
        $statements = [];

        foreach ($batches as $batch) {
            $statements[] = $this->buildBatchStatement($table, $batch, $driver);
        }

        return implode("\n", $statements);
    }

    protected function buildBatchStatement(string $table, array $batch, string $driver): string
    {
        $firstRow = $batch[0];
        $columns = implode(', ', array_map(fn ($col) => $this->quoteIdentifier($col, $driver), array_keys($firstRow)));

        $valueRows = [];
        foreach ($batch as $row) {
            $valueRows[] = '('.$this->formatValues(array_values($row), $driver).')';
        }

        $valuesClause = implode(', ', $valueRows);

        return $this->buildStatement($table, $firstRow, $columns, $valuesClause, $driver);
    }

    protected function buildStatement(string $table, array $row, string $columns, string $valuesClause, string $driver): string
    {
        $quotedTable = $this->quoteIdentifier($table, $driver);

        return match ($driver) {
            'sqlite' => "INSERT OR REPLACE INTO {$quotedTable} ({$columns}) VALUES {$valuesClause};",
            'mysql', 'mariadb' => $this->buildMysqlUpsert($table, $row, $columns, $valuesClause, $driver),
            'pgsql' => $this->buildPostgresUpsert($table, $row, $columns, $valuesClause, $driver),
            default => "INSERT INTO {$quotedTable} ({$columns}) VALUES {$valuesClause};",
        };
    }

    protected function buildMysqlUpsert(string $table, array $row, string $columns, string $valuesClause, string $driver): string
    {
        $updates = [];
        foreach (array_keys($row) as $col) {
            if ($col !== 'id') {
                $quotedCol = $this->quoteIdentifier($col, $driver);
                $updates[] = "{$quotedCol} = VALUES({$quotedCol})";
            }
        }

        $updateClause = ! empty($updates) ? ' ON DUPLICATE KEY UPDATE '.implode(', ', $updates) : '';
        $quotedTable = $this->quoteIdentifier($table, $driver);

        return "INSERT INTO {$quotedTable} ({$columns}) VALUES {$valuesClause}{$updateClause};";
    }

    protected function buildPostgresUpsert(string $table, array $row, string $columns, string $valuesClause, string $driver): string
    {
        $updates = [];
        foreach (array_keys($row) as $col) {
            if ($col !== 'id') {
                $quotedCol = $this->quoteIdentifier($col, $driver);
                $updates[] = "{$quotedCol} = EXCLUDED.{$quotedCol}";
            }
        }

        $updateClause = ! empty($updates)
            ? ' ON CONFLICT (id) DO UPDATE SET '.implode(', ', $updates)
            : ' ON CONFLICT DO NOTHING';
        $quotedTable = $this->quoteIdentifier($table, $driver);

        return "INSERT INTO {$quotedTable} ({$columns}) VALUES {$valuesClause}{$updateClause};";
    }

    protected function formatValues(array $values, string $driver): string
    {
        return implode(', ', array_map(function ($value) {
            if (is_null($value)) {
                return 'NULL';
            }
            if (is_numeric($value)) {
                return $value;
            }

            return $this->quoteValue($value);
        }, $values));
    }

    protected function quoteValue(string $value): string
    {
        if ($this->connection === null) {
            $this->connection = config('database.default');
        }

        return DB::connection($this->connection)->getPdo()->quote($value);
    }

    protected function quoteIdentifier(string $identifier, string $driver): string
    {
        return match ($driver) {
            'pgsql' => '"'.str_replace('"', '""', $identifier).'"',
            default => '`'.str_replace('`', '``', $identifier).'`',
        };
    }
}
