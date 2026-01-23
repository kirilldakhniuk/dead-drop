<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use KirillDakhniuk\DeadDrop\Actions\Import\ExecuteImportStatements;
use KirillDakhniuk\DeadDrop\Actions\Import\ParseSqlStatements;
use KirillDakhniuk\DeadDrop\Actions\Storage\DownloadFromCloudStorage;

/**
 * @internal
 */
final class Importer
{
    public function __construct(
        protected ParseSqlStatements $parser,
        protected ExecuteImportStatements $executor,
        protected DownloadFromCloudStorage $cloudDownloader
    ) {}

    public function importFromFile(string $filePath, ?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        if (! File::exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = File::get($filePath);
        $statements = $this->parser->execute($content);

        return $this->executor->execute($statements, $connection, $filePath);
    }

    public function importFromCloud(string $cloudPath, string $disk, ?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        $storage = Storage::disk($disk);

        if (! $storage->exists($cloudPath)) {
            throw new \InvalidArgumentException("File not found in cloud storage: {$cloudPath}");
        }

        $content = $storage->get($cloudPath);
        $statements = $this->parser->execute($content);

        return $this->executor->execute($statements, $connection, $cloudPath);
    }

    public function importAsync(string $filePath, ?string $connection = null): string
    {
        if (! File::exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $importId = \Illuminate\Support\Str::uuid()->toString();

        ExportStatus::create($importId, 'import', [
            'file' => basename($filePath),
            'connection' => $connection ?? config('database.default'),
        ]);

        Jobs\ImportFileJob::dispatch($filePath, $connection, $importId);

        return $importId;
    }

    public function importFromCloudAsync(string $cloudPath, string $disk, ?string $connection = null): string
    {
        $importId = \Illuminate\Support\Str::uuid()->toString();

        ExportStatus::create($importId, 'import', [
            'cloud_path' => $cloudPath,
            'disk' => $disk,
            'connection' => $connection ?? config('database.default'),
        ]);

        try {
            $tempPath = $this->cloudDownloader->execute($cloudPath, $disk);
        } catch (\InvalidArgumentException $e) {
            ExportStatus::fail($importId, $e->getMessage());
            throw $e;
        }

        Jobs\ImportFileJob::dispatch($tempPath, $connection, $importId);

        return $importId;
    }
}
