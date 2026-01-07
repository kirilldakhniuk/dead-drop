<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\Exporter;

beforeEach(function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.storage.disk', 'local');

    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
    ]);

    Config::set('dead-drop.tables.posts', [
        'columns' => ['id', 'title', 'content', 'status'],
        'where' => [
            ['status', '=', 'published'],
        ],
        'order_by' => 'created_at DESC',
        'limit' => 5,
    ]);
});

afterEach(function () {
    $outputPath = sys_get_temp_dir().'/dead-drop-test';
    if (File::exists($outputPath)) {
        File::deleteDirectory($outputPath);
    }
});

test('exportTable exports users table to SQL with upsert', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    expect($result)->toBeArray()
        ->and($result['table'])->toBe('users')
        ->and($result['records'])->toBe(5)
        ->and($result['file'])->toContain('users.sql')
        ->and($result['cloud_path'])->toBeNull()
        ->and($result['storage_disk'])->toBeNull()
        ->and(File::exists($result['file']))->toBeTrue();

    $content = File::get($result['file']);

    expect($content)->toContain('INSERT OR REPLACE INTO `users`')
        ->and($content)->toContain('User 1')
        ->and($content)->toContain('user1@example.com');
});

test('exportTable respects where conditions', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('posts', 'testing', $outputPath);

    expect($result['records'])->toBe(5);

    $content = File::get($result['file']);
    expect($content)->toContain('published')
        ->and($content)->not->toContain('draft');
});

test('exportTable respects limit configuration', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('posts', 'testing', $outputPath);

    expect($result['records'])->toBe(5);
});

test('exportTable respects column selection', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('posts', 'testing', $outputPath);

    $content = File::get($result['file']);
    expect($content)->toContain('INSERT OR REPLACE INTO `posts`')
        ->and($content)->toContain('`id`, `title`, `content`, `status`')
        ->and($content)->not->toContain('user_id');
});

test('exportTable throws exception for unconfigured table', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $exporter->exportTable('non_existent_table', 'testing', $outputPath);
})->throws(InvalidArgumentException::class, 'Table non_existent_table is not configured for export');

test('exportTable throws exception for disabled table', function () {
    Config::set('dead-drop.tables.disabled_table', false);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $exporter->exportTable('disabled_table', 'testing', $outputPath);
})->throws(InvalidArgumentException::class, 'Table disabled_table is not configured for export');

test('exportTable creates output directory if it does not exist', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test/nested/path';

    expect(File::exists($outputPath))->toBeFalse();

    $exporter->exportTable('users', 'testing', $outputPath);

    expect(File::exists($outputPath))->toBeTrue()
        ->and(File::isDirectory($outputPath))->toBeTrue();
});

test('exportTable returns correct file size', function () {
    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    expect($result['size'])->toBeGreaterThan(0)
        ->and($result['size'])->toBe(File::size($result['file']));
});

test('exportTable uploads to cloud storage when configured', function () {
    Config::set('dead-drop.storage.disk', 'testing-cloud');
    Config::set('dead-drop.storage.path', 'backups');
    Config::set('filesystems.disks.testing-cloud', [
        'driver' => 'local',
        'root' => sys_get_temp_dir().'/cloud-storage',
    ]);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    expect($result['cloud_path'])->toBe('backups/users.sql')
        ->and($result['storage_disk'])->toBe('testing-cloud')
        ->and(File::exists($result['file']))->toBeTrue();

    $cloudFile = sys_get_temp_dir().'/cloud-storage/backups/users.sql';
    expect(File::exists($cloudFile))->toBeTrue();

    File::deleteDirectory(sys_get_temp_dir().'/cloud-storage');
});

test('exportTable deletes local file after cloud upload when configured', function () {
    Config::set('dead-drop.storage.disk', 'testing-cloud');
    Config::set('dead-drop.storage.path', 'backups');
    Config::set('dead-drop.storage.delete_local_after_upload', true);
    Config::set('filesystems.disks.testing-cloud', [
        'driver' => 'local',
        'root' => sys_get_temp_dir().'/cloud-storage',
    ]);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    expect($result['cloud_path'])->toBe('backups/users.sql')
        ->and($result['local_deleted'])->toBeTrue()
        ->and(File::exists($result['file']))->toBeFalse();

    $cloudFile = sys_get_temp_dir().'/cloud-storage/backups/users.sql';
    expect(File::exists($cloudFile))->toBeTrue();

    File::deleteDirectory(sys_get_temp_dir().'/cloud-storage');
});

test('exportTable censors sensitive fields with fake data when configured', function () {
    Config::set('dead-drop.tables.users.censor', ['email', 'name']);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    $content = File::get($result['file']);

    expect($content)->not->toContain('user1@example.com')
        ->and($content)->not->toContain('User 1')
        ->and($content)->not->toContain('user2@example.com')
        ->and($content)->not->toContain('User 2');

    expect($content)->toContain('INSERT OR REPLACE INTO `users`');

    $matches = [];
    preg_match_all('/\'([^\']*@[^\']*)\'/', $content, $matches);
    expect($matches[1])->not->toBeEmpty(); // Should have fake emails
});

test('exportTable only censors specified fields', function () {
    Config::set('dead-drop.tables.users.censor', ['email']);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    $content = File::get($result['file']);

    expect($content)->not->toContain('user1@example.com')
        ->and($content)->not->toContain('user2@example.com');

    expect($content)->toContain('User 1')
        ->and($content)->toContain('User 2');

    $matches = [];
    preg_match_all('/\'([^\']*@[^\']*)\'/', $content, $matches);
    expect($matches[1])->not->toBeEmpty();
});

test('exportTable works without censoring when not configured', function () {
    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
    ]);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    $content = File::get($result['file']);

    expect($content)->toContain('user1@example.com')
        ->and($content)->toContain('User 1')
        ->and($content)->toContain('user2@example.com');
});

test('exportTable supports custom faker methods', function () {
    Config::set('dead-drop.tables.users.censor', [
        'email' => 'freeEmail',
        'name' => 'firstName',
    ]);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    $content = File::get($result['file']);

    expect($content)->not->toContain('user1@example.com')
        ->and($content)->not->toContain('User 1');

    expect($content)->toContain('INSERT OR REPLACE INTO `users`');
});

test('exportTable includes default values for required fields', function () {
    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
        'defaults' => ['password' => 'password'],
    ]);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    $content = File::get($result['file']);

    expect($content)->toContain('`password`');

    expect($content)->not->toContain("'password'");

    expect($content)->toMatch('/\'\$2y\$/');

    expect($content)->toContain('INSERT OR REPLACE INTO `users`');
});

test('exportTable hashes password fields automatically', function () {
    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
        'defaults' => ['password' => 'secret123'],
    ]);

    $exporter = new Exporter;
    $outputPath = sys_get_temp_dir().'/dead-drop-test';

    $result = $exporter->exportTable('users', 'testing', $outputPath);

    $content = File::get($result['file']);

    expect($content)->not->toContain('secret123')
        ->and($content)->toMatch('/\'\$2y\$/');
});
