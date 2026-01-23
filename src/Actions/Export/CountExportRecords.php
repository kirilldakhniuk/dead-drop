<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use KirillDakhniuk\DeadDrop\Exporter;
use KirillDakhniuk\DeadDrop\ExportRequest;

class CountExportRecords
{
    public function __construct(
        protected Exporter $exporter
    ) {}

    public function execute(ExportRequest $request): int
    {
        $total = 0;

        foreach ($request->tables as $table) {
            $total += $this->exporter->countRecords($table, $request->connection, $request->overrides);
        }

        return $total;
    }
}
