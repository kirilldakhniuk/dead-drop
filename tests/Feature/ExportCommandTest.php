<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.output_path', storage_path('app/dead-drop'));

    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
    ]);

    Config::set('dead-drop.tables.posts', [
        'columns' => ['id', 'title', 'content'],
    ]);
});

afterEach(function () {
    $outputPath = config('dead-drop.output_path');
    if (File::exists($outputPath)) {
        File::deleteDirectory($outputPath);
    }
});

test('export command is available', function () {
    $this->artisan('list')
        ->expectsOutputToContain('dead-drop:export')
        ->assertExitCode(0);
});

test('export command has correct description', function () {
    $this->artisan('dead-drop:export --help')
        ->expectsOutputToContain('Export database tables to SQL')
        ->assertExitCode(0);
});
