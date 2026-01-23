<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Import;

use Illuminate\Support\Facades\DB;

class ExecuteImportStatements
{
    public function execute(array $statements, string $connection, string $source): array
    {
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
}
