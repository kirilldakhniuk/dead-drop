<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class BuildExportQuery
{
    public function execute(string $table, string $connection, ?array $overrides = null): Builder
    {
        $config = $this->resolveTableConfig($table, $overrides);

        return $this->buildQuery($table, $connection, $config);
    }

    public function resolveTableConfig(string $table, ?array $overrides = null): array
    {
        $config = config("dead-drop.tables.{$table}");

        if (! $config || $config === false) {
            throw new \InvalidArgumentException("Table {$table} is not configured for export");
        }

        if (! $overrides) {
            return $config;
        }

        if (isset($overrides['where'])) {
            $whereOverrides = $overrides['where'];
            unset($overrides['where']);
            $config = array_merge($config, $overrides);
            $config['where'] = array_merge($config['where'] ?? [], $whereOverrides);
        } else {
            $config = array_merge($config, $overrides);
        }

        return $config;
    }

    protected function buildQuery(string $table, string $connection, array $config): Builder
    {
        $query = DB::connection($connection)->table($table);

        if (isset($config['columns']) && $config['columns'] !== '*') {
            $query->select($config['columns']);
        }

        if (isset($config['where'])) {
            foreach ($config['where'] as $condition) {
                $query->where(...$condition);
            }
        }

        if (isset($config['order_by'])) {
            [$column, $direction] = array_pad(explode(' ', $config['order_by']), 2, 'ASC');
            $query->orderBy($column, trim($direction));
        }

        if (isset($config['limit'])) {
            $query->limit($config['limit']);
        }

        if (empty($query->orders)) {
            $query->orderBy('id');
        }

        return $query;
    }
}
