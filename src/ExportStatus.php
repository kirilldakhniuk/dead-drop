<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @internal
 */
final class ExportStatus
{
    public static function make(): ExportStatusBuilder
    {
        return new ExportStatusBuilder;
    }

    public static function create(string $id, string $type, array $metadata): void
    {
        DB::table('dead_drop_exports')->insert([
            'id' => $id,
            'type' => $type,
            'status' => 'pending',
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function updateStatus(string $id, string $status): void
    {
        DB::table('dead_drop_exports')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }

    public static function updateProgress(string $id, int $current, ?int $total = null): void
    {
        $data = ['progress_current' => $current, 'updated_at' => now()];

        if ($total !== null) {
            $data['progress_total'] = $total;
        }

        DB::table('dead_drop_exports')->where('id', $id)->update($data);
    }

    public static function complete(string $id, array $result): void
    {
        DB::table('dead_drop_exports')
            ->where('id', $id)
            ->update([
                'status' => 'completed',
                'result' => json_encode($result),
                'updated_at' => now(),
            ]);
    }

    public static function fail(string $id, string $error): void
    {
        DB::table('dead_drop_exports')
            ->where('id', $id)
            ->update([
                'status' => 'failed',
                'error' => $error,
                'updated_at' => now(),
            ]);
    }

    public static function get(string $id): ?array
    {
        $record = DB::table('dead_drop_exports')->where('id', $id)->first();

        if (! $record) {
            return null;
        }

        return self::transformRecord($record);
    }

    public static function getRecent(int $limit = 10): array
    {
        return DB::table('dead_drop_exports')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($record) => self::transformRecord($record))
            ->all();
    }

    private static function transformRecord(object $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type,
            'status' => $record->status,
            'metadata' => json_decode($record->metadata, true),
            'progress_current' => $record->progress_current,
            'progress_total' => $record->progress_total,
            'result' => $record->result ? json_decode($record->result, true) : null,
            'error' => $record->error,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }
}

class ExportStatusBuilder
{
    private ?string $id = null;

    private string $type = 'export';

    private array $metadata = [];

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function forExport(): self
    {
        $this->type = 'export';

        return $this;
    }

    public function forImport(): self
    {
        $this->type = 'import';

        return $this;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function forTable(string $table): self
    {
        $this->metadata['table'] = $table;

        return $this;
    }

    public function forTables(array $tables): self
    {
        $this->metadata['tables'] = $tables;
        $this->metadata['type'] = 'single-file';

        return $this;
    }

    public function withConnection(string $connection): self
    {
        $this->metadata['connection'] = $connection;

        return $this;
    }

    public function create(): string
    {
        $id = $this->id ?? Str::uuid()->toString();

        ExportStatus::create($id, $this->type, $this->metadata);

        return $id;
    }
}
