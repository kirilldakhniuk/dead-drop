<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Carbon\Carbon;
use Illuminate\Console\OutputStyle;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ExportPrompter
{
    public function __construct(
        protected ?OutputStyle $output = null
    ) {}

    public function gather(?string $connection = null): ExportRequest
    {
        return new ExportRequest(
            tables: $this->promptForTables(),
            connection: $connection ?? config('database.default'),
            outputPath: config('dead-drop.output_path', storage_path('app/dead-drop')),
            overrides: $this->promptForOverrides(),
        );
    }

    protected function promptForTables(): array
    {
        $availableTables = $this->getEnabledTables();

        if (empty($availableTables)) {
            throw new \InvalidArgumentException('No tables configured for export');
        }

        if (count($availableTables) === 1) {
            return $availableTables;
        }

        $exportAll = confirm(
            label: 'Export all configured tables?',
            default: true,
            hint: 'Select "No" to pick specific tables'
        );

        if ($exportAll) {
            return $availableTables;
        }

        $selected = multiselect(
            label: 'Which tables do you want to export?',
            options: $availableTables,
            hint: 'Space to toggle, Enter to confirm'
        );

        if (empty($selected)) {
            throw new \InvalidArgumentException('No tables selected for export');
        }

        return $selected;
    }

    protected function promptForOverrides(): ?array
    {
        $dateConditions = $this->promptForDateRange();

        return $dateConditions ? ['where' => $dateConditions] : null;
    }

    protected function promptForDateRange(): ?array
    {
        $option = select(
            label: 'Filter by date range?',
            options: [
                'none' => 'No filter (export all)',
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last_week' => 'Last 7 days',
                'last_month' => 'Last 30 days',
                'custom' => 'Custom interval',
            ],
            default: 'none'
        );

        if ($option === 'none') {
            return null;
        }

        if ($option === 'custom') {
            return $this->promptForCustomDateRange();
        }

        return $this->getPresetDateRange($option);
    }

    protected function promptForCustomDateRange(): ?array
    {
        $dateFrom = $this->promptForDate('Filter from date (optional)');
        $dateTo = $this->promptForDate('Filter to date (optional)');

        if (! $dateFrom && ! $dateTo) {
            return null;
        }

        $conditions = [];

        if ($dateFrom) {
            $conditions[] = ['created_at', '>=', $dateFrom->startOfDay()->toDateTimeString()];
        }

        if ($dateTo) {
            $conditions[] = ['created_at', '<=', $dateTo->endOfDay()->toDateTimeString()];
        }

        return $conditions;
    }

    protected function promptForDate(string $label): ?Carbon
    {
        while (true) {
            $input = text(
                label: $label,
                hint: 'Examples: 2024-01-01, last month, 30 days ago (or leave empty to skip)'
            );

            if (empty(trim($input))) {
                return null;
            }

            try {
                return Carbon::parse($input);
            } catch (\Exception) {
                $this->output?->error('Invalid date format. Please try again.');
            }
        }
    }

    protected function getPresetDateRange(string $option): array
    {
        return match ($option) {
            'today' => [
                ['created_at', '>=', Carbon::today()->startOfDay()->toDateTimeString()],
            ],
            'yesterday' => [
                ['created_at', '>=', Carbon::yesterday()->startOfDay()->toDateTimeString()],
                ['created_at', '<', Carbon::today()->startOfDay()->toDateTimeString()],
            ],
            'last_week' => [
                ['created_at', '>=', Carbon::now()->subDays(7)->startOfDay()->toDateTimeString()],
            ],
            'last_month' => [
                ['created_at', '>=', Carbon::now()->subDays(30)->startOfDay()->toDateTimeString()],
            ],
            default => [],
        };
    }

    protected function getEnabledTables(): array
    {
        return array_keys(array_filter(
            config('dead-drop.tables', []),
            fn ($config) => $config !== false
        ));
    }
}
