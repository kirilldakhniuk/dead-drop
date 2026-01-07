<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

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
