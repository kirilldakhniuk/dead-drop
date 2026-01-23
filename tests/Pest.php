<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

const TEST_OUTPUT_PATH = '/tmp/dead-drop-test';
const TEST_CLOUD_PATH = '/tmp/cloud-storage';

function createTestDatabase(): void
{
    $db = app('db');
    $db->getSchemaBuilder()->create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    $db->getSchemaBuilder()->create('posts', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained();
        $table->string('title');
        $table->text('content');
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    $db->getSchemaBuilder()->create('categories', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->timestamps();
    });
}

function seedTestData(): void
{
    $db = app('db');

    for ($i = 1; $i <= 5; $i++) {
        $db->table('users')->insert([
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    for ($i = 1; $i <= 10; $i++) {
        $db->table('posts')->insert([
            'user_id' => ($i % 5) + 1,
            'title' => "Post {$i}",
            'content' => "Content for post {$i}",
            'status' => $i % 2 === 0 ? 'published' : 'draft',
            'created_at' => now()->subDays($i),
            'updated_at' => now()->subDays($i),
        ]);
    }

    $categories = ['Technology', 'Health', 'Finance', 'Travel', 'Food'];
    foreach ($categories as $category) {
        $db->table('categories')->insert([
            'name' => $category,
            'slug' => strtolower($category),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function createDeadDropExportsTable(): void
{
    $db = app('db');

    if ($db->getSchemaBuilder()->hasTable('dead_drop_exports')) {
        return;
    }

    $db->getSchemaBuilder()->create('dead_drop_exports', function ($table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->string('status');
        $table->json('metadata')->nullable();
        $table->integer('progress_current')->default(0);
        $table->integer('progress_total')->nullable();
        $table->json('result')->nullable();
        $table->text('error')->nullable();
        $table->timestamps();

        $table->index('status');
        $table->index('created_at');
    });
}

function testUuid(): string
{
    return Str::uuid()->toString();
}

function cleanupTestDirectory(string $path): void
{
    if (File::exists($path)) {
        File::deleteDirectory($path);
    }
}

function setupCloudStorage(string $diskName = 'testing-cloud'): void
{
    Config::set("dead-drop.storage.disk", $diskName);
    Config::set("filesystems.disks.{$diskName}", [
        'driver' => 'local',
        'root' => TEST_CLOUD_PATH,
    ]);
}

function createTestSqlFile(string $path, int $userId, string $name, string $email): string
{
    File::ensureDirectoryExists(dirname($path));
    $hashedPassword = bcrypt('password');
    $sql = "INSERT OR REPLACE INTO `users` (`id`, `name`, `email`, `password`, `created_at`, `updated_at`) VALUES ({$userId}, '{$name}', '{$email}', '{$hashedPassword}', '2024-01-01 00:00:00', '2024-01-01 00:00:00');\n";
    File::put($path, $sql);

    return $path;
}

function configureUsersTable(array $options = []): void
{
    Config::set('dead-drop.tables.users', array_merge([
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
    ], $options));
}

function configurePostsTable(array $options = []): void
{
    Config::set('dead-drop.tables.posts', array_merge([
        'columns' => ['id', 'title', 'content', 'status'],
    ], $options));
}
