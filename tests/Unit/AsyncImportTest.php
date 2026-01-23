<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use KirillDakhniuk\DeadDrop\ExportStatus;
use KirillDakhniuk\DeadDrop\Importer;
use KirillDakhniuk\DeadDrop\Jobs\ImportFileJob;

beforeEach(function () {
    createTestDatabase();
    createDeadDropExportsTable();

    Config::set('dead-drop.storage.disk', 'local');
    Config::set('dead-drop.output_path', TEST_OUTPUT_PATH);
    Config::set('dead-drop.queue.connection', 'sync');
    Config::set('dead-drop.queue.queue_name', 'exports');

    Queue::fake();
});

afterEach(fn () => cleanupTestDirectory(TEST_OUTPUT_PATH));

describe('importAsync', function () {
    beforeEach(function () {
        File::ensureDirectoryExists(TEST_OUTPUT_PATH);
        File::put(TEST_OUTPUT_PATH.'/test.sql', "INSERT INTO users (id, name) VALUES (1, 'Test');");
    });

    test('dispatches ImportFileJob and returns import ID', function () {
        $filePath = TEST_OUTPUT_PATH.'/test.sql';
        $importer = app(Importer::class);

        $importId = $importer->importAsync($filePath);

        expect($importId)
            ->toBeString()
            ->toMatch('/^[a-f0-9-]{36}$/');

        Queue::assertPushed(ImportFileJob::class, fn ($job) => $job->filePath === $filePath);
    });

    test('creates status record', function () {
        $importer = app(Importer::class);

        $importId = $importer->importAsync(TEST_OUTPUT_PATH.'/test.sql');

        $status = ExportStatus::get($importId);

        expect($status)
            ->not->toBeNull()
            ->type->toBe('import')
            ->status->toBe('pending')
            ->and($status['metadata']['file'])->toBe('test.sql');
    });

    test('throws exception for non-existent file', function () {
        app(Importer::class)->importAsync('/nonexistent/file.sql');
    })->throws(InvalidArgumentException::class, 'File not found');
});

describe('importFromCloudAsync', function () {
    test('creates status record with cloud info', function () {
        setupCloudStorage();

        $cloudPath = 'backups/test.sql';
        Storage::disk('testing-cloud')->put($cloudPath, "INSERT INTO users (id, name) VALUES (1, 'Test');");

        $importer = app(Importer::class);

        $importId = $importer->importFromCloudAsync($cloudPath, 'testing-cloud');

        $status = ExportStatus::get($importId);

        expect($status)
            ->not->toBeNull()
            ->type->toBe('import')
            ->and($status['metadata'])
            ->cloud_path->toBe($cloudPath)
            ->disk->toBe('testing-cloud');

        Queue::assertPushed(ImportFileJob::class);

        cleanupTestDirectory(TEST_CLOUD_PATH);
        cleanupTestDirectory(storage_path('app/temp'));
    });
});

describe('ImportFileJob', function () {
    test('updates status to completed', function () {
        Queue::fake([]);

        File::ensureDirectoryExists(TEST_OUTPUT_PATH);
        $filePath = TEST_OUTPUT_PATH.'/test.sql';
        File::put($filePath, "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (100, 'Test User', 'test@example.com', 'password', '2024-01-01', '2024-01-01');");

        $importer = app(Importer::class);
        $importId = $importer->importAsync($filePath);

        $job = new ImportFileJob($filePath, 'testing', $importId);
        $job->handle($importer);

        $status = ExportStatus::get($importId);

        expect($status)
            ->status->toBe('completed')
            ->and($status['result'])->toBeArray();
    });

    test('completes with failed count for invalid SQL', function () {
        Queue::fake([]);

        File::ensureDirectoryExists(TEST_OUTPUT_PATH);
        $filePath = TEST_OUTPUT_PATH.'/invalid.sql';
        File::put($filePath, 'INVALID SQL SYNTAX HERE;');

        $importId = testUuid();
        ExportStatus::create($importId, 'import', ['file' => 'invalid.sql']);

        $importer = app(Importer::class);
        $job = new ImportFileJob($filePath, 'testing', $importId);
        $job->handle($importer);

        $status = ExportStatus::get($importId);

        expect($status)
            ->status->toBe('completed')
            ->and($status['result']['failed'])->toBeGreaterThan(0);
    });
});
