<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Concerns;

trait ReportsProgress
{
    protected function exportWithProgressBar(
        int $total,
        callable $callback,
        string $label = 'Processing'
    ): mixed {
        if (! $this->output) {
            return $callback(null);
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %memory:6s%');
        $bar->start();

        $result = $callback(function ($current) use ($bar) {
            $bar->setProgress($current);
        });

        $bar->finish();
        $this->newLine();

        return $result;
    }
}
