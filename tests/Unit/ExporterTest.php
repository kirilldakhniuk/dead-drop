<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\Exporter;

beforeEach(function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.storage.disk', 'local');
    configureUsersTable();
    configurePostsTable([
        'where' => [['status', '=', 'published']],
        'order_by' => 'created_at DESC',
        'limit' => 5,
    ]);
});

afterEach(fn () => cleanupTestDirectory(TEST_OUTPUT_PATH));

test('exportTable exports users table to SQL with upsert', function () {
    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    expect($result)
        ->table->toBe('users')
        ->records->toBe(5)
        ->file->toContain('users.sql')
        ->cloud_path->toBeNull()
        ->storage_disk->toBeNull()
        ->and(File::exists($result['file']))->toBeTrue();

    $content = File::get($result['file']);

    expect($content)
        ->toContain('INSERT OR REPLACE INTO `users`')
        ->toContain('User 1')
        ->toContain('user1@example.com');
});

test('exportTable respects where conditions', function () {
    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('posts', 'testing', TEST_OUTPUT_PATH);

    expect($result['records'])->toBe(5);

    $content = File::get($result['file']);
    expect($content)
        ->toContain('published')
        ->not->toContain('draft');
});

test('exportTable respects limit configuration', function () {
    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('posts', 'testing', TEST_OUTPUT_PATH);

    expect($result['records'])->toBe(5);
});

test('exportTable respects column selection', function () {
    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('posts', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);
    expect($content)
        ->toContain('INSERT OR REPLACE INTO `posts`')
        ->toContain('`id`, `title`, `content`, `status`')
        ->not->toContain('user_id');
});

test('exportTable throws exception for unconfigured table', function () {
    $exporter = app(Exporter::class);

    $exporter->exportTable('non_existent_table', 'testing', TEST_OUTPUT_PATH);
})->throws(InvalidArgumentException::class, 'Table non_existent_table is not configured for export');

test('exportTable throws exception for disabled table', function () {
    Config::set('dead-drop.tables.disabled_table', false);

    $exporter = app(Exporter::class);

    $exporter->exportTable('disabled_table', 'testing', TEST_OUTPUT_PATH);
})->throws(InvalidArgumentException::class, 'Table disabled_table is not configured for export');

test('exportTable creates output directory if it does not exist', function () {
    $exporter = app(Exporter::class);
    $nestedPath = TEST_OUTPUT_PATH.'/nested/path';

    expect(File::exists($nestedPath))->toBeFalse();

    $exporter->exportTable('users', 'testing', $nestedPath);

    expect(File::exists($nestedPath))->toBeTrue();
    expect(File::isDirectory($nestedPath))->toBeTrue();
});

test('exportTable returns correct file size', function () {
    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    expect($result['size'])
        ->toBeGreaterThan(0)
        ->toBe(File::size($result['file']));
});

test('exportTable uploads to cloud storage when configured', function () {
    setupCloudStorage();
    Config::set('dead-drop.storage.path', 'backups');

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    expect($result)
        ->cloud_path->toBe('backups/users.sql')
        ->storage_disk->toBe('testing-cloud')
        ->and(File::exists($result['file']))->toBeTrue();

    expect(File::exists(TEST_CLOUD_PATH.'/backups/users.sql'))->toBeTrue();

    cleanupTestDirectory(TEST_CLOUD_PATH);
});

test('exportTable deletes local file after cloud upload when configured', function () {
    setupCloudStorage();
    Config::set('dead-drop.storage.path', 'backups');
    Config::set('dead-drop.storage.delete_local_after_upload', true);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    expect($result)
        ->cloud_path->toBe('backups/users.sql')
        ->local_deleted->toBeTrue()
        ->and(File::exists($result['file']))->toBeFalse();

    expect(File::exists(TEST_CLOUD_PATH.'/backups/users.sql'))->toBeTrue();

    cleanupTestDirectory(TEST_CLOUD_PATH);
});

test('exportTable censors sensitive fields with fake data when configured', function () {
    Config::set('dead-drop.tables.users.censor', ['email', 'name']);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);

    expect($content)
        ->not->toContain('user1@example.com')
        ->not->toContain('User 1')
        ->not->toContain('user2@example.com')
        ->not->toContain('User 2')
        ->toContain('INSERT OR REPLACE INTO `users`');

    preg_match_all('/\'([^\']*@[^\']*)\'/', $content, $matches);
    expect($matches[1])->not->toBeEmpty();
});

test('exportTable only censors specified fields', function () {
    Config::set('dead-drop.tables.users.censor', ['email']);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);

    expect($content)
        ->not->toContain('user1@example.com')
        ->not->toContain('user2@example.com')
        ->toContain('User 1')
        ->toContain('User 2');

    preg_match_all('/\'([^\']*@[^\']*)\'/', $content, $matches);
    expect($matches[1])->not->toBeEmpty();
});

test('exportTable works without censoring when not configured', function () {
    configureUsersTable();

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);

    expect($content)
        ->toContain('user1@example.com')
        ->toContain('User 1')
        ->toContain('user2@example.com');
});

test('exportTable supports custom faker methods', function () {
    Config::set('dead-drop.tables.users.censor', [
        'email' => 'freeEmail',
        'name' => 'firstName',
    ]);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);

    expect($content)
        ->not->toContain('user1@example.com')
        ->not->toContain('User 1')
        ->toContain('INSERT OR REPLACE INTO `users`');
});

test('exportTable includes default values for required fields', function () {
    configureUsersTable(['defaults' => ['password' => 'password']]);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);

    expect($content)
        ->toContain('`password`')
        ->not->toContain("'password'")
        ->toMatch('/\'\$2y\$/')
        ->toContain('INSERT OR REPLACE INTO `users`');
});

test('exportTable hashes password fields automatically', function () {
    configureUsersTable(['defaults' => ['password' => 'secret123']]);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH);

    $content = File::get($result['file']);

    expect($content)
        ->not->toContain('secret123')
        ->toMatch('/\'\$2y\$/');
});

test('exportTable accepts override parameter', function () {
    $exporter = app(Exporter::class);

    $overrides = [
        'where' => [
            ['created_at', '>=', now()->subDays(1)->toDateTimeString()],
        ],
    ];

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH, $overrides);

    expect($result)->table->toBe('users');
});

test('exportTable merges override where with config where', function () {
    Config::set('dead-drop.tables.users.where', [
        ['email', 'like', '%@example.com'],
    ]);

    $exporter = app(Exporter::class);

    $overrides = [
        'where' => [
            ['created_at', '>=', now()->subDays(7)->toDateTimeString()],
        ],
    ];

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH, $overrides);

    expect($result)->table->toBe('users');
});

test('exportTable override replaces limit', function () {
    Config::set('dead-drop.tables.users.limit', 10);

    $exporter = app(Exporter::class);

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH, ['limit' => 2]);

    expect($result['records'])->toBe(2);
});

test('exportTable date filtering actually filters records', function () {
    $db = app('db');
    $db->table('users')->truncate();

    // Insert old users (40 and 35 days ago)
    collect([40, 35])->each(function ($daysAgo, $index) use ($db) {
        $db->table('users')->insert([
            'name' => "Old User ".($index + 1),
            'email' => "old".($index + 1)."@example.com",
            'password' => 'password',
            'created_at' => now()->subDays($daysAgo)->toDateTimeString(),
            'updated_at' => now()->subDays($daysAgo)->toDateTimeString(),
        ]);
    });

    // Insert recent users (20, 10, and 5 days ago)
    collect([20, 10, 5])->each(function ($daysAgo, $index) use ($db) {
        $db->table('users')->insert([
            'name' => "Recent User ".($index + 1),
            'email' => "recent".($index + 1)."@example.com",
            'password' => 'password',
            'created_at' => now()->subDays($daysAgo)->toDateTimeString(),
            'updated_at' => now()->subDays($daysAgo)->toDateTimeString(),
        ]);
    });

    $exporter = app(Exporter::class);

    $overrides = [
        'where' => [
            ['created_at', '>=', now()->subDays(30)->startOfDay()->toDateTimeString()],
        ],
    ];

    $result = $exporter->exportTable('users', 'testing', TEST_OUTPUT_PATH, $overrides);

    expect($result['records'])->toBe(3);

    $content = file_get_contents($result['file']);

    expect($content)
        ->toContain('Recent User 1')
        ->toContain('Recent User 2')
        ->toContain('Recent User 3')
        ->not->toContain('Old User 1')
        ->not->toContain('Old User 2');
});
