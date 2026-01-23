<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Storage;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UploadToCloudStorage
{
    public function execute(string $localFilePath, ?string $disk = null): ?array
    {
        $disk = $disk ?? config('dead-drop.storage.disk');

        if (! $disk || $disk === 'local') {
            return null;
        }

        $cloudPath = $this->upload($localFilePath, $disk);

        $result = [
            'cloud_path' => $cloudPath,
            'storage_disk' => $disk,
            'local_deleted' => false,
        ];

        if (config('dead-drop.storage.delete_local_after_upload', false)) {
            File::delete($localFilePath);
            $result['local_deleted'] = true;
        }

        return $result;
    }

    protected function upload(string $localFilePath, string $disk): string
    {
        $storagePath = config('dead-drop.storage.path', 'dead-drop');
        $filename = basename($localFilePath);
        $cloudPath = trim($storagePath, '/').'/'.$filename;

        Storage::disk($disk)->put($cloudPath, File::get($localFilePath));

        return $cloudPath;
    }
}
