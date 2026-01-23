<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Commands;

use Illuminate\Console\Command;
use KirillDakhniuk\DeadDrop\Concerns\FormatsBytes;
use KirillDakhniuk\DeadDrop\ExportStatus;

class StatusCommand extends Command
{
    use FormatsBytes;

    protected $signature = 'dead-drop:status {id?}';

    protected $description = 'Check export/import status';

    public function handle(): int
    {
        $id = $this->argument('id');

        if (! $id) {
            $this->listRecentExports();

            return self::SUCCESS;
        }

        $status = ExportStatus::get($id);

        if (! $status) {
            $this->error("Export not found: {$id}");

            return self::FAILURE;
        }

        $this->displayStatus($status);

        return self::SUCCESS;
    }

    protected function listRecentExports(): void
    {
        $recent = ExportStatus::getRecent(10);

        if (empty($recent)) {
            $this->info('No recent exports found.');

            return;
        }

        $this->info('Recent exports and imports:');
        $this->newLine();

        $tableData = array_map(fn ($export) => [
            substr($export['id'], 0, 8).'...',
            ucfirst($export['type']),
            $this->resolveTargetLabel($export),
            $this->formatStatus($export['status']),
            $export['created_at'],
        ], $recent);

        $this->table(['ID', 'Type', 'Target', 'Status', 'Created'], $tableData);
        $this->newLine();
        $this->info('Use "php artisan dead-drop:status <id>" to see full details');
    }

    protected function resolveTargetLabel(array $export): string
    {
        $metadata = $export['metadata'] ?? [];

        if ($export['type'] === 'export') {
            return $metadata['table'] ?? $metadata['tables'][0] ?? 'unknown';
        }

        return $metadata['file'] ?? 'unknown';
    }

    protected function displayStatus(array $status): void
    {
        $this->info('Export/Import Status');
        $this->newLine();

        $tableData = [
            ['ID', $status['id']],
            ['Type', ucfirst($status['type'])],
            ['Status', $this->formatStatus($status['status'])],
        ];

        if ($status['metadata']) {
            foreach ($status['metadata'] as $key => $value) {
                $displayValue = is_array($value) ? implode(', ', $value) : $value;
                $tableData[] = [ucfirst($key), $displayValue];
            }
        }

        if ($status['progress_total']) {
            $percentage = round(($status['progress_current'] / $status['progress_total']) * 100, 1);
            $tableData[] = ['Progress', "{$status['progress_current']}/{$status['progress_total']} ({$percentage}%)"];
        } elseif ($status['progress_current'] > 0) {
            $tableData[] = ['Progress', $status['progress_current'].' records'];
        }

        $tableData[] = ['Created', $status['created_at']];
        $tableData[] = ['Updated', $status['updated_at']];

        $this->table(['Property', 'Value'], $tableData);

        $this->newLine();
        $this->displayStatusMessage($status);
    }

    protected function displayStatusMessage(array $status): void
    {
        match ($status['status']) {
            'processing' => $this->info('Still processing... Refresh with: php artisan dead-drop:status '.$status['id']),
            'completed' => $this->displayCompletedStatus($status),
            'failed' => $this->displayFailedStatus($status),
            default => null,
        };
    }

    protected function displayCompletedStatus(array $status): void
    {
        $this->info('Completed successfully!');

        if ($status['result']) {
            $this->displayResult($status['result']);
        }
    }

    protected function displayFailedStatus(array $status): void
    {
        $this->error('Failed:');
        $this->line($status['error'] ?? 'Unknown error');
    }

    protected function displayResult(array $result): void
    {
        $this->newLine();

        $fields = [
            'table' => 'Table',
            'records' => 'Records',
            'file' => 'File',
            'size' => 'Size',
            'cloud_path' => 'Cloud Path',
            'storage_disk' => 'Storage',
        ];

        $resultData = [];
        foreach ($fields as $key => $label) {
            if (isset($result[$key])) {
                $value = $key === 'size' ? $this->formatBytes($result[$key]) : $result[$key];
                $resultData[] = [$label, $value];
            }
        }

        if (! empty($resultData)) {
            $this->table(['Property', 'Value'], $resultData);
        }
    }

    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'pending' => '⏸ Pending',
            'processing' => '⏳ Processing',
            'completed' => '✓ Completed',
            'failed' => '✗ Failed',
            default => $status,
        };
    }
}
