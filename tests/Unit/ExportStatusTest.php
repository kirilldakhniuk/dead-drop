<?php

use KirillDakhniuk\DeadDrop\ExportStatus;

beforeEach(fn () => createDeadDropExportsTable());

describe('create', function () {
    test('inserts status record with pending status', function () {
        $id = testUuid();

        ExportStatus::create($id, 'export', ['table' => 'users']);

        $status = ExportStatus::get($id);

        expect($status)
            ->not->toBeNull()
            ->id->toBe($id)
            ->type->toBe('export')
            ->status->toBe('pending')
            ->and($status['metadata']['table'])->toBe('users');
    });
});

describe('updateStatus', function () {
    test('changes status field', function () {
        $id = testUuid();
        
        ExportStatus::create($id, 'export', ['table' => 'users']);

        ExportStatus::updateStatus($id, 'processing');

        expect(ExportStatus::get($id))->status->toBe('processing');
    });
});

describe('updateProgress', function () {
    test('updates both current and total progress', function () {
        $id = testUuid();
        
        ExportStatus::create($id, 'export', ['table' => 'users']);

        ExportStatus::updateProgress($id, 50, 100);

        $status = ExportStatus::get($id);
            
        expect($status)
            ->progress_current->toBe(50)
            ->progress_total->toBe(100);
    });

    test('updates only current when total not provided', function () {
        $id = testUuid();
        
        ExportStatus::create($id, 'export', ['table' => 'users']);

        ExportStatus::updateProgress($id, 25);

        $status = ExportStatus::get($id);
            
        expect($status)
            ->progress_current->toBe(25)
            ->progress_total->toBeNull();
    });
});

describe('complete', function () {
    test('marks status as completed with result', function () {
        $id = testUuid();
        
        ExportStatus::create($id, 'export', ['table' => 'users']);

        ExportStatus::complete($id, [
            'table' => 'users',
            'records' => 100,
            'file' => '/path/to/file.sql',
        ]);

        $status = ExportStatus::get($id);
            
        expect($status)
            ->status->toBe('completed')
            ->and($status['result'])
            ->toBeArray()
            ->table->toBe('users')
            ->records->toBe(100);
    });
});

describe('fail', function () {
    test('marks status as failed with error message', function () {
        $id = testUuid();
        
        ExportStatus::create($id, 'export', ['table' => 'users']);

        ExportStatus::fail($id, 'Something went wrong');

        $status = ExportStatus::get($id);
            
        expect($status)
            ->status->toBe('failed')
            ->error->toBe('Something went wrong');
    });
});

describe('get', function () {
    test('returns null for non-existent id', function () {
        expect(ExportStatus::get('non-existent-id'))->toBeNull();
    });
});

describe('getRecent', function () {
    test('returns recent exports ordered by date', function () {
        for ($i = 1; $i <= 5; $i++) {
            ExportStatus::create(testUuid(), 'export', ['table' => "table{$i}"]);
        }

        $recent = ExportStatus::getRecent(3);

        expect($recent)->toBeArray()->toHaveCount(3);
    });

    test('returns empty array when no exports', function () {
        expect(ExportStatus::getRecent())->toBeArray()->toBeEmpty();
    });
});
