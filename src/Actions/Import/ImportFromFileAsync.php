<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Import;

use KirillDakhniuk\DeadDrop\Importer;
use KirillDakhniuk\DeadDrop\ImportRequest;

class ImportFromFileAsync
{
    public function __construct(
        protected Importer $importer
    ) {}

    public function execute(ImportRequest $request): string
    {
        if ($request->isCloud) {
            return $this->importer->importFromCloudAsync(
                $request->source,
                $request->cloudDisk,
                $request->connection
            );
        }

        return $this->importer->importAsync($request->source, $request->connection);
    }
}
