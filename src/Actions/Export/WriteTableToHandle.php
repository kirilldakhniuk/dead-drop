<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use Illuminate\Database\Query\Builder;

class WriteTableToHandle
{
    public function __construct(
        protected TransformRowData $transformer,
        protected GenerateUpsertSql $sqlGenerator
    ) {}

    public function execute(
        string $table,
        Builder $query,
        mixed $handle,
        array $config,
        string $driver,
        ?string $connection = null,
        ?callable $progressCallback = null,
        string $primaryKey = 'id'
    ): int {
        if ($connection !== null) {
            $this->sqlGenerator->setConnection($connection);
        }

        $this->sqlGenerator->setPrimaryKey($primaryKey);

        $recordCount = 0;
        $chunkSize = config('dead-drop.performance.chunk_size', 1000);

        $query->chunk($chunkSize, function ($chunk) use ($handle, &$recordCount, $table, $driver, $config, $progressCallback) {
            foreach ($chunk as $row) {
                $rowArray = $this->transformer->execute((array) $row, $config);

                $statement = $this->sqlGenerator->forRow($table, $rowArray, $driver);
                fwrite($handle, $statement."\n");

                $recordCount++;

                if ($progressCallback && $recordCount % 100 === 0) {
                    $progressCallback($recordCount);
                }
            }

            gc_collect_cycles();
        });

        return $recordCount;
    }
}
