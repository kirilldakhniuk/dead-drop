<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use KirillDakhniuk\DeadDrop\Exporter;
use KirillDakhniuk\DeadDrop\ExportStatus;
use KirillDakhniuk\DeadDrop\Jobs\ExportTableJob;
use KirillDakhniuk\DeadDrop\Jobs\ExportTablesToSingleFileJob;

beforeEach(function () {
    createTestDatabase();
    createDeadDropExportsTable();
    seedTestData();

    Config::set('dead-drop.storage.disk', 'local');
    Config::set('dead-drop.output_path', TEST_OUTPUT_PATH);
    Config::set('dead-drop.queue.connection', 'sync');
    Config::set('dead-drop.queue.queue_name', 'exports');

    configureUsersTable();
    configurePostsTable([
        'where' => [['status', '=', 'published']],
    ]);

    Queue::fake();
});

afterEach(fn () => cleanupTestDirectory(TEST_OUTPUT_PATH));

describe('exportAsync', function () {
    test('dispatches ExportTableJob and returns export ID', function () {
        $exporter = app(Exporter::class);

        $exportId = $exporter->exportAsync('users');

        expect($exportId)
            ->toBeString()
            ->toMatch('/^[a-f0-9-]{36}$/');

        Queue::assertPushed(ExportTableJob::class, fn ($job) => $job->table === 'users');
    });

    test('creates status record', function () {
        $exporter = app(Exporter::class);

        $exportId = $exporter->exportAsync('users');

        $status = ExportStatus::get($exportId);

        expect($status)
            ->not->toBeNull()
            ->type->toBe('export')
            ->status->toBe('pending')
            ->and($status['metadata']['table'])->toBe('users');
    });
});

describe('exportAllAsync', function () {
    test('dispatches jobs for all enabled tables', function () {
        $exporter = app(Exporter::class);

        $exportIds = $exporter->exportAllAsync();

        expect($exportIds)
            ->toBeArray()
            ->toHaveKeys(['users', 'posts']);

        Queue::assertPushed(ExportTableJob::class, 2);
    });

    test('skips disabled tables', function () {
        Config::set('dead-drop.tables.disabled', false);

        $exporter = app(Exporter::class);

        $exportIds = $exporter->exportAllAsync();

        expect($exportIds)->not->toHaveKey('disabled');
    });
});

describe('exportAllToSingleFileAsync', function () {
    test('dispatches ExportTablesToSingleFileJob', function () {
        $exporter = app(Exporter::class);

        $exportId = $exporter->exportAllToSingleFileAsync();

        expect($exportId)
            ->toBeString()
            ->toMatch('/^[a-f0-9-]{36}$/');

        Queue::assertPushed(ExportTablesToSingleFileJob::class);
    });

    test('creates status record with table list', function () {
        $exporter = app(Exporter::class);

        $exportId = $exporter->exportAllToSingleFileAsync();

        $status = ExportStatus::get($exportId);

        expect($status)
            ->not->toBeNull()
            ->type->toBe('export')
            ->status->toBe('pending')
            ->and($status['metadata'])
            ->type->toBe('single-file')
            ->and($status['metadata']['tables'])
            ->toContain('users')
            ->toContain('posts');
    });
});

describe('ExportTableJob', function () {
    test('updates status to completed after processing', function () {
        Queue::fake([]);

        $exporter = app(Exporter::class);
        $exportId = $exporter->exportAsync('users');

        $job = new ExportTableJob('users', 'testing', TEST_OUTPUT_PATH, null, $exportId);
        $job->handle($exporter);

        expect(ExportStatus::get($exportId))->status->toBe('completed');
    });

    test('marks status as completed with result', function () {
        Queue::fake([]);

        $exporter = app(Exporter::class);
        $exportId = $exporter->exportAsync('users');

        $job = new ExportTableJob('users', 'testing', TEST_OUTPUT_PATH, null, $exportId);
        $job->handle($exporter);

        $status = ExportStatus::get($exportId);

        expect($status)
            ->status->toBe('completed')
            ->and($status['result'])
            ->toBeArray()
            ->table->toBe('users')
            ->records->toBe(5);
    });

    test('marks status as failed on exception', function () {
        Queue::fake([]);

        $exporter = app(Exporter::class);
        $exportId = testUuid();
        ExportStatus::create($exportId, 'export', ['table' => 'nonexistent']);

        $job = new ExportTableJob('nonexistent', 'testing', TEST_OUTPUT_PATH, null, $exportId);

        try {
            $job->handle($exporter);
        } catch (\Exception) {
            // Expected
        }

        $status = ExportStatus::get($exportId);

        expect($status)
            ->status->toBe('failed')
            ->error->toContain('not configured');
    });
});
