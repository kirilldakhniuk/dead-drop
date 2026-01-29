<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use KirillDakhniuk\DeadDrop\Concerns\FormatsBytes;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class ImportPrompter
{
    use FormatsBytes;

    protected string $connection;

    public function gather(?string $connection = null): ?ImportRequest
    {
        $this->connection = $connection ?? config('database.default');

        if (! $this->confirmWarning()) {
            return null;
        }

        $source = $this->promptForSource();

        return $source === 'cloud'
            ? $this->gatherCloudImport()
            : $this->gatherLocalImport();
    }

    protected function confirmWarning(): bool
    {
        warning('WARNING: Importing will execute SQL statements in your database!');

        return confirm('Do you want to continue?', false);
    }

    protected function promptForSource(): string
    {
        $storageDisk = config('dead-drop.storage.disk', 'local');

        if ($storageDisk === 'local') {
            return 'local';
        }

        return select(
            label: 'Import from',
            options: [
                'local' => 'Local file',
                'cloud' => "Cloud storage ({$storageDisk})",
            ],
        );
    }

    protected function gatherLocalImport(): ?ImportRequest
    {
        $filePath = $this->promptForLocalFile();

        if (! $filePath) {
            return null;
        }

        if (! $this->confirmImport(basename($filePath), $this->formatFileSize($filePath))) {
            return null;
        }

        return ImportRequest::fromLocal($filePath, $this->connection);
    }

    protected function gatherCloudImport(): ?ImportRequest
    {
        $storageDisk = config('dead-drop.storage.disk');
        $cloudPath = $this->promptForCloudPath();

        if (! $this->confirmCloudImport($cloudPath, $storageDisk)) {
            return null;
        }

        return ImportRequest::fromCloud($cloudPath, $storageDisk, $this->connection);
    }

    protected function promptForLocalFile(): ?string
    {
        $defaultPath = config('dead-drop.output_path', storage_path('app/dead-drop'));
        $sqlFiles = File::glob($defaultPath.'/*.sql');

        if (empty($sqlFiles)) {
            return $this->promptForCustomPath($defaultPath);
        }

        return $this->promptForFileSelection($sqlFiles);
    }

    protected function promptForCustomPath(string $defaultPath): ?string
    {
        warning("No SQL files found in: {$defaultPath}");

        $customPath = text(
            label: 'Enter the full path to the SQL file',
            required: true,
        );

        if (! File::exists($customPath)) {
            throw new \InvalidArgumentException('File not found: '.$customPath);
        }

        return $customPath;
    }

    protected function promptForFileSelection(array $sqlFiles): ?string
    {
        $options = [];
        foreach ($sqlFiles as $file) {
            $options[$file] = basename($file).' ('.$this->formatFileSize($file).')';
        }
        $options['custom'] = 'Enter custom path...';

        $selected = select(
            label: 'Select SQL file to import',
            options: $options,
        );

        if ($selected === 'custom') {
            $customPath = text(
                label: 'Enter the full path to the SQL file',
                required: true,
            );

            if (! File::exists($customPath)) {
                throw new \InvalidArgumentException('File not found: '.$customPath);
            }

            return $customPath;
        }

        return $selected;
    }

    protected function promptForCloudPath(): string
    {
        $storagePath = config('dead-drop.storage.path', 'dead-drop');
        $disk = config('dead-drop.storage.disk');
        $storage = Storage::disk($disk);

        $sqlFiles = collect($storage->files($storagePath))
            ->filter(fn ($file) => str_ends_with($file, '.sql'))
            ->sortDesc()
            ->values()
            ->all();

        if (empty($sqlFiles)) {
            warning("No SQL files found in cloud storage: {$storagePath}");

            return text(
                label: 'Enter the cloud storage path (e.g., dead-drop/export.sql)',
                default: $storagePath.'/',
                required: true,
            );
        }

        $options = [];
        foreach ($sqlFiles as $file) {
            $size = $this->formatBytes($storage->size($file));
            $options[$file] = basename($file)." ({$size})";
        }
        $options['custom'] = 'Enter custom path...';

        $selected = select(
            label: 'Select SQL file to import',
            options: $options,
        );

        if ($selected === 'custom') {
            return text(
                label: 'Enter the cloud storage path',
                default: $storagePath.'/',
                required: true,
            );
        }

        return $selected;
    }

    protected function confirmImport(string $filename, string $size): bool
    {
        return confirm(
            label: "Import {$filename} ({$size})?",
            default: false
        );
    }

    protected function confirmCloudImport(string $cloudPath, string $disk): bool
    {
        return confirm(
            label: "Import {$cloudPath} from {$disk}?",
            default: false
        );
    }

    protected function formatFileSize(string $filePath): string
    {
        return $this->formatBytes(File::size($filePath));
    }
}
