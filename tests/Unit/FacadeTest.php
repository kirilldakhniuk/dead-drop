<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\Facades\DeadDrop;

beforeEach(function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.storage.disk', 'local');
    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
    ]);
});

afterEach(function () {
    $outputPath = sys_get_temp_dir().'/dead-drop-test';
    if (File::exists($outputPath)) {
        File::deleteDirectory($outputPath);
    }
});

test('facade exports table with defaults from config', function () {
    $result = DeadDrop::export('users');

    expect($result)->toBeArray()
        ->and($result['table'])->toBe('users')
        ->and($result['records'])->toBe(5)
        ->and(File::exists($result['file']))->toBeTrue();
});

test('facade accepts date range overrides', function () {
    $result = DeadDrop::export('users', [
        'where' => [
            ['created_at', '>=', now()->subDays(7)->toDateTimeString()],
        ],
    ]);

    expect($result)->toBeArray()
        ->and($result['table'])->toBe('users')
        ->and(File::exists($result['file']))->toBeTrue();
});

test('facade merges where conditions with config', function () {
    Config::set('dead-drop.tables.users.where', [
        ['email', 'like', '%@example.com'],
    ]);

    $result = DeadDrop::export('users', [
        'where' => [
            ['created_at', '>=', now()->subDays(7)->toDateTimeString()],
        ],
    ]);

    // Both filters should be applied
    expect($result)->toBeArray()
        ->and($result['table'])->toBe('users');
});

test('facade accepts custom connection and output path', function () {
    $customPath = sys_get_temp_dir().'/custom-export';

    $result = DeadDrop::export('users', null, 'testing', $customPath);

    expect($result['file'])->toContain('custom-export')
        ->and(File::exists($result['file']))->toBeTrue();

    File::deleteDirectory($customPath);
});
