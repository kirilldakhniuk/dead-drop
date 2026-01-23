<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use KirillDakhniuk\DeadDrop\Exporter;
use KirillDakhniuk\DeadDrop\ExportRequest;

class ExportTablesToFile
{
    public function __construct(
        protected Exporter $exporter
    ) {}

    public function execute(ExportRequest $request, ?callable $progressCallback = null): array
    {
        return $this->exporter->exportTablesToSingleFile(
            $request->tables,
            $request->outputPath,
            $request->connection,
            $request->overrides,
            $progressCallback
        );
    }
}
