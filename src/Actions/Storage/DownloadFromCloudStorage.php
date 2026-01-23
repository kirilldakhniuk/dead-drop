<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Storage;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DownloadFromCloudStorage
{
    public function execute(string $cloudPath, string $disk): string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($cloudPath)) {
            throw new \InvalidArgumentException("File not found in cloud storage: {$cloudPath}");
        }

        $tempPath = storage_path('app/temp/'.basename($cloudPath));
        File::ensureDirectoryExists(dirname($tempPath));
        File::put($tempPath, $storage->get($cloudPath));

        return $tempPath;
    }
}
