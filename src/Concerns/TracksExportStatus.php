<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Concerns;

use KirillDakhniuk\DeadDrop\ExportStatus;

trait TracksExportStatus
{
    protected function trackStatus(callable $operation): void
    {
        $statusId = $this->getStatusId();

        if ($statusId) {
            ExportStatus::updateStatus($statusId, 'processing');
        }

        try {
            $result = $operation();

            if ($statusId) {
                ExportStatus::complete($statusId, $result);
            }
        } catch (\Exception $e) {
            if ($statusId) {
                ExportStatus::fail($statusId, $e->getMessage());
            }

            throw $e;
        }
    }

    protected function reportProgress(int $current): void
    {
        $statusId = $this->getStatusId();

        if ($statusId) {
            ExportStatus::updateProgress($statusId, $current);
        }
    }

    protected function getStatusId(): ?string
    {
        return $this->exportId ?? $this->importId ?? null;
    }
}
