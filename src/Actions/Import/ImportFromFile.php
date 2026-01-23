<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Import;

use KirillDakhniuk\DeadDrop\Importer;
use KirillDakhniuk\DeadDrop\ImportRequest;

class ImportFromFile
{
    public function __construct(
        protected Importer $importer
    ) {}

    public function execute(ImportRequest $request): array
    {
        if ($request->isCloud) {
            return $this->importer->importFromCloud(
                $request->source,
                $request->cloudDisk,
                $request->connection
            );
        }

        return $this->importer->importFromFile($request->source, $request->connection);
    }
}
