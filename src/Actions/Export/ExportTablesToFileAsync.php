<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use KirillDakhniuk\DeadDrop\Exporter;
use KirillDakhniuk\DeadDrop\ExportRequest;

class ExportTablesToFileAsync
{
    public function __construct(
        protected Exporter $exporter
    ) {}

    public function execute(ExportRequest $request): string
    {
        return $this->exporter->exportTablesToSingleFileAsync(
            $request->tables,
            $request->outputPath,
            $request->connection,
            $request->overrides
        );
    }
}
