<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use KirillDakhniuk\DeadDrop\Importer;

const IMPORT_TEST_PATH = '/tmp/dead-drop-import-test';

beforeEach(function () {
    createTestDatabase();
    Config::set('dead-drop.storage.disk', 'local');
});

afterEach(fn () => cleanupTestDirectory(IMPORT_TEST_PATH));

test('importFromFile imports SQL statements successfully', function () {
    $importer = app(Importer::class);

    createTestSqlFile(IMPORT_TEST_PATH.'/user100.sql', 100, 'Test User', 'test@example.com');

    // Create a file with multiple users
    File::ensureDirectoryExists(IMPORT_TEST_PATH);
    $hashedPassword = bcrypt('password');
    $sql = "-- Test import\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (100, 'Test User', 'test@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (101, 'Another User', 'another@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    $filePath = IMPORT_TEST_PATH.'/test-import.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');

    expect($result)
        ->executed->toBe(2)
        ->failed->toBe(0)
        ->total->toBe(2);

    $user = DB::connection('testing')->table('users')->where('id', 100)->first();
    expect($user)
        ->not->toBeNull()
        ->name->toBe('Test User')
        ->email->toBe('test@example.com');
});

test('importFromFile throws exception for non-existent file', function () {
    app(Importer::class)->importFromFile('/non/existent/file.sql');
})->throws(InvalidArgumentException::class, 'File not found');

test('importFromFile handles SQL comments correctly', function () {
    $importer = app(Importer::class);
    File::ensureDirectoryExists(IMPORT_TEST_PATH);

    $hashedPassword = bcrypt('password');
    $sql = "-- This is a comment\n";
    $sql .= "/* Multi-line\n   comment */\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (200, 'Comment Test', 'comment@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    File::put(IMPORT_TEST_PATH.'/comments.sql', $sql);

    $result = $importer->importFromFile(IMPORT_TEST_PATH.'/comments.sql', 'testing');

    expect($result)
        ->executed->toBe(1)
        ->failed->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 200)->first();
    expect($user)
        ->not->toBeNull()
        ->name->toBe('Comment Test');
});

test('importFromFile handles failed statements', function () {
    $importer = app(Importer::class);
    File::ensureDirectoryExists(IMPORT_TEST_PATH);

    $hashedPassword = bcrypt('password');
    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (300, 'Valid User', 'valid@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    $sql .= "INSERT INTO `nonexistent_table` (`id`) VALUES (1);\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (301, 'Another Valid', 'another@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    File::put(IMPORT_TEST_PATH.'/with-errors.sql', $sql);

    $result = $importer->importFromFile(IMPORT_TEST_PATH.'/with-errors.sql', 'testing');

    expect($result)
        ->executed->toBe(2)
        ->failed->toBe(1)
        ->and($result['errors'])->toHaveCount(1);
});

test('importFromCloud imports from cloud storage', function () {
    setupCloudStorage();

    $hashedPassword = bcrypt('password');
    $cloudPath = 'imports/test.sql';
    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (400, 'Cloud User', 'cloud@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    Storage::disk('testing-cloud')->put($cloudPath, $sql);

    $importer = app(Importer::class);
    $result = $importer->importFromCloud($cloudPath, 'testing-cloud', 'testing');

    expect($result)
        ->executed->toBe(1)
        ->failed->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 400)->first();
    expect($user)
        ->not->toBeNull()
        ->name->toBe('Cloud User');

    cleanupTestDirectory(TEST_CLOUD_PATH);
});

test('importFromCloud throws exception for non-existent file', function () {
    setupCloudStorage();

    app(Importer::class)->importFromCloud('nonexistent.sql', 'testing-cloud');
})->throws(InvalidArgumentException::class, 'File not found in cloud storage');

test('importFromFile handles semicolons in quoted strings', function () {
    $importer = app(Importer::class);

    $filePath = createTestSqlFile(IMPORT_TEST_PATH.'/semicolon.sql', 500, 'Name; with semicolon', 'test@example.com');

    $result = $importer->importFromFile($filePath, 'testing');

    expect($result)
        ->executed->toBe(1)
        ->failed->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 500)->first();
    expect($user)
        ->not->toBeNull()
        ->name->toBe('Name; with semicolon');
});

test('upsert allows re-importing same data without errors', function () {
    $importer = app(Importer::class);

    $filePath = createTestSqlFile(IMPORT_TEST_PATH.'/upsert.sql', 600, 'Test User', 'test@example.com');

    $result1 = $importer->importFromFile($filePath, 'testing');
    expect($result1)
        ->executed->toBe(1)
        ->failed->toBe(0);

    $result2 = $importer->importFromFile($filePath, 'testing');
    expect($result2)
        ->executed->toBe(1)
        ->failed->toBe(0);

    expect(DB::connection('testing')->table('users')->where('id', 600)->count())->toBe(1);
});

test('upsert updates existing records with partial columns', function () {
    seedTestData();

    $importer = app(Importer::class);

    $filePath = createTestSqlFile(IMPORT_TEST_PATH.'/update.sql', 1, 'Updated Name', 'updated@example.com');

    $result = $importer->importFromFile($filePath, 'testing');
    expect($result)
        ->executed->toBe(1)
        ->failed->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 1)->first();
    expect($user)
        ->name->toBe('Updated Name')
        ->email->toBe('updated@example.com');
});

test('defaults prevent NOT NULL constraint violations on import', function () {
    $importer = app(Importer::class);

    $filePath = createTestSqlFile(IMPORT_TEST_PATH.'/defaults.sql', 700, 'Test User', 'test@example.com');

    $result = $importer->importFromFile($filePath, 'testing');
    expect($result)
        ->executed->toBe(1)
        ->failed->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 700)->first();
    expect($user)
        ->not->toBeNull()
        ->name->toBe('Test User')
        ->and($user->password)->toStartWith('$2y$');
});
