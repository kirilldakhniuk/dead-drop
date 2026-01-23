<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

readonly class ImportRequest
{
    public function __construct(
        public string $source,
        public string $connection,
        public bool $isCloud = false,
        public ?string $cloudDisk = null,
    ) {}

    public static function make(): ImportRequestBuilder
    {
        return new ImportRequestBuilder;
    }

    public static function fromLocal(string $filePath, ?string $connection = null): self
    {
        return self::make()
            ->source($filePath)
            ->connection($connection)
            ->build();
    }

    public static function fromCloud(string $cloudPath, string $disk, ?string $connection = null): self
    {
        return self::make()
            ->source($cloudPath)
            ->fromCloudDisk($disk)
            ->connection($connection)
            ->build();
    }
}

class ImportRequestBuilder
{
    private string $source = '';

    private ?string $connection = null;

    private bool $isCloud = false;

    private ?string $cloudDisk = null;

    public function source(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function connection(?string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function fromCloudDisk(string $disk): self
    {
        $this->isCloud = true;
        $this->cloudDisk = $disk;

        return $this;
    }

    public function build(): ImportRequest
    {
        return new ImportRequest(
            source: $this->source,
            connection: $this->connection ?? config('database.default'),
            isCloud: $this->isCloud,
            cloudDisk: $this->cloudDisk,
        );
    }
}
