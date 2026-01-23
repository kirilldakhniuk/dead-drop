<?php

use KirillDakhniuk\DeadDrop\ExportStatus;

beforeEach(fn () => createDeadDropExportsTable());

describe('command availability', function () {
    test('status command is available', function () {
        $this->artisan('dead-drop:status')->assertSuccessful();
    });

    test('status command is listed in artisan commands', function () {
        $this->artisan('list')
            ->expectsOutputToContain('dead-drop:status')
            ->assertSuccessful();
    });
});

describe('listing exports', function () {
    test('shows no recent exports message when empty', function () {
        $this->artisan('dead-drop:status')
            ->expectsOutputToContain('No recent exports found')
            ->assertSuccessful();
    });

    test('lists recent exports when present', function () {
        ExportStatus::create(testUuid(), 'export', ['table' => 'users']);

        $this->artisan('dead-drop:status')
            ->expectsOutputToContain('Recent exports')
            ->expectsOutputToContain('Export')
            ->assertSuccessful();
    });
});

describe('viewing specific export', function () {
    test('shows details for existing export', function () {
        $id = testUuid();
        ExportStatus::create($id, 'export', ['table' => 'users']);

        $this->artisan('dead-drop:status', ['id' => $id])
            ->expectsOutputToContain('Export/Import Status')
            ->expectsOutputToContain($id)
            ->assertSuccessful();
    });

    test('shows error for non-existent export', function () {
        $this->artisan('dead-drop:status', ['id' => 'non-existent-id'])
            ->expectsOutputToContain('Export not found')
            ->assertFailed();
    });
});

describe('status display', function () {
    test('shows progress for processing export', function () {
        $id = testUuid();
        ExportStatus::create($id, 'export', ['table' => 'users']);
        ExportStatus::updateStatus($id, 'processing');
        ExportStatus::updateProgress($id, 50, 100);

        $this->artisan('dead-drop:status', ['id' => $id])
            ->expectsOutputToContain('Processing')
            ->expectsOutputToContain('50/100')
            ->assertSuccessful();
    });

    test('shows completed status', function () {
        $id = testUuid();
        ExportStatus::create($id, 'export', ['table' => 'users']);
        ExportStatus::complete($id, [
            'table' => 'users',
            'records' => 100,
            'file' => '/path/to/users.sql',
            'size' => 1024,
        ]);

        $this->artisan('dead-drop:status', ['id' => $id])
            ->expectsOutputToContain('Completed')
            ->assertSuccessful();
    });

    test('shows failed status with error message', function () {
        $id = testUuid();
        ExportStatus::create($id, 'export', ['table' => 'users']);
        ExportStatus::fail($id, 'Database connection failed');

        $this->artisan('dead-drop:status', ['id' => $id])
            ->expectsOutputToContain('Failed')
            ->expectsOutputToContain('Database connection failed')
            ->assertSuccessful();
    });
});
