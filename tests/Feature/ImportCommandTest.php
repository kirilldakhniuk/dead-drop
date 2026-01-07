<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.output_path', storage_path('app/dead-drop'));
});

afterEach(function () {
    $outputPath = config('dead-drop.output_path');
    if (File::exists($outputPath)) {
        File::deleteDirectory($outputPath);
    }
});

test('import command is available', function () {
    $this->artisan('list')
        ->expectsOutputToContain('dead-drop:import')
        ->assertExitCode(0);
});

test('import command has correct description', function () {
    $this->artisan('dead-drop:import --help')
        ->expectsOutputToContain('Import SQL files into the database')
        ->assertExitCode(0);
});
