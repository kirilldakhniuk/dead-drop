<?php

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.output_path', TEST_OUTPUT_PATH);
    configureUsersTable();
    configurePostsTable(['columns' => ['id', 'title', 'content']]);
});

afterEach(fn () => cleanupTestDirectory(TEST_OUTPUT_PATH));

test('export command is available', function () {
    $this->artisan('list')
        ->expectsOutputToContain('dead-drop:export')
        ->assertExitCode(0);
});

test('export command has correct description', function () {
    $this->artisan('dead-drop:export --help')
        ->expectsOutputToContain('Export database tables to a single SQL file')
        ->assertExitCode(0);
});
