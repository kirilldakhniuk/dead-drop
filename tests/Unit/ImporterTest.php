<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use KirillDakhniuk\DeadDrop\Importer;

beforeEach(function () {
    createTestDatabase();
    Config::set('dead-drop.storage.disk', 'local');
});

afterEach(function () {
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    if (File::exists($outputPath)) {
        File::deleteDirectory($outputPath);
    }
});

test('importFromFile imports SQL statements successfully', function () {
    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "-- Test import\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (100, 'Test User', 'test@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (101, 'Another User', 'another@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/test-import.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');

    expect($result)->toBeArray()
        ->and($result['executed'])->toBe(2)
        ->and($result['failed'])->toBe(0)
        ->and($result['total'])->toBe(2);

    $user = DB::connection('testing')->table('users')->where('id', 100)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com');
});

test('importFromFile throws exception for non-existent file', function () {
    $importer = new Importer;

    $importer->importFromFile('/non/existent/file.sql');
})->throws(InvalidArgumentException::class, 'File not found');

test('importFromFile handles SQL comments correctly', function () {
    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "-- This is a comment\n";
    $sql .= "/* Multi-line\n   comment */\n";
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (200, 'Comment Test', 'comment@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/comments.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');

    expect($result['executed'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 200)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Comment Test');
});

test('importFromFile handles failed statements', function () {
    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (300, 'Valid User', 'valid@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    $sql .= "INSERT INTO `nonexistent_table` (`id`) VALUES (1);\n"; // This will fail
    $sql .= "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (301, 'Another Valid', 'another@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/with-errors.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');

    expect($result['executed'])->toBe(2)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1);
});

test('importFromCloud imports from cloud storage', function () {
    Config::set('dead-drop.storage.disk', 'testing-cloud');
    Config::set('filesystems.disks.testing-cloud', [
        'driver' => 'local',
        'root' => sys_get_temp_dir().'/cloud-storage',
    ]);

    $hashedPassword = bcrypt('password');
    $cloudPath = 'imports/test.sql';
    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (400, 'Cloud User', 'cloud@test.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    Storage::disk('testing-cloud')->put($cloudPath, $sql);

    $importer = new Importer;
    $result = $importer->importFromCloud($cloudPath, 'testing-cloud', 'testing');

    expect($result['executed'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 400)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Cloud User');

    File::deleteDirectory(sys_get_temp_dir().'/cloud-storage');
});

test('importFromCloud throws exception for non-existent file', function () {
    Config::set('dead-drop.storage.disk', 'testing-cloud');
    Config::set('filesystems.disks.testing-cloud', [
        'driver' => 'local',
        'root' => sys_get_temp_dir().'/cloud-storage',
    ]);

    $importer = new Importer;
    $importer->importFromCloud('nonexistent.sql', 'testing-cloud');
})->throws(InvalidArgumentException::class, 'File not found in cloud storage');

test('importFromFile handles semicolons in quoted strings', function () {
    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (500, 'Name; with semicolon', 'test@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/quoted-semicolon.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');

    expect($result['executed'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 500)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Name; with semicolon');
});

test('upsert allows re-importing same data without errors', function () {
    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (600, 'Test User', 'test@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/upsert-test.sql';
    File::put($filePath, $sql);

    $result1 = $importer->importFromFile($filePath, 'testing');
    expect($result1['executed'])->toBe(1)
        ->and($result1['failed'])->toBe(0);

    $result2 = $importer->importFromFile($filePath, 'testing');
    expect($result2['executed'])->toBe(1)
        ->and($result2['failed'])->toBe(0);

    $count = DB::connection('testing')->table('users')->where('id', 600)->count();
    expect($count)->toBe(1);
});

test('upsert updates existing records with partial columns', function () {
    seedTestData();

    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (1, 'Updated Name', 'updated@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/update-test.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');
    expect($result['executed'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 1)->first();
    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com');
});

test('defaults prevent NOT NULL constraint violations on import', function () {
    $importer = new Importer;
    $outputPath = sys_get_temp_dir().'/dead-drop-import-test';
    File::ensureDirectoryExists($outputPath);

    $hashedPassword = bcrypt('password');

    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES (700, 'Test User', 'test@example.com', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";

    $filePath = $outputPath.'/defaults-test.sql';
    File::put($filePath, $sql);

    $result = $importer->importFromFile($filePath, 'testing');
    expect($result['executed'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $user = DB::connection('testing')->table('users')->where('id', 700)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->password)->toStartWith('$2y$');
});
