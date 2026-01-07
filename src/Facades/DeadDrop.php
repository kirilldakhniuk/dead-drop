<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array export(string $table, ?array $overrides = null, ?string $connection = null, ?string $outputPath = null)
 * @method static array exportAll(?string $outputPath = null, ?string $connection = null, ?array $overrides = null)
 * @method static array exportTable(string $table, string $connection, string $outputPath, ?array $overrides = null)
 *
 * @see \KirillDakhniuk\DeadDrop\Exporter
 */
class DeadDrop extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dead-drop';
    }
}
