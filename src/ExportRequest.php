<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

readonly class ExportRequest
{
    public function __construct(
        public array $tables,
        public string $connection,
        public string $outputPath,
        public ?array $overrides = null,
    ) {}

    public static function make(): ExportRequestBuilder
    {
        return new ExportRequestBuilder;
    }

    public static function fromConfig(array $tables, ?array $overrides = null): self
    {
        return self::make()
            ->tables($tables)
            ->withOverrides($overrides)
            ->build();
    }
}

class ExportRequestBuilder
{
    private array $tables = [];

    private ?string $connection = null;

    private ?string $outputPath = null;

    private ?array $overrides = null;

    public function tables(array $tables): self
    {
        $this->tables = $tables;

        return $this;
    }

    public function connection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function outputPath(string $path): self
    {
        $this->outputPath = $path;

        return $this;
    }

    public function withOverrides(?array $overrides): self
    {
        $this->overrides = $overrides;

        return $this;
    }

    public function build(): ExportRequest
    {
        return new ExportRequest(
            tables: $this->tables,
            connection: $this->connection ?? config('database.default'),
            outputPath: $this->outputPath ?? config('dead-drop.output_path', storage_path('app/dead-drop')),
            overrides: $this->overrides,
        );
    }
}
