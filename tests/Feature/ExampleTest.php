<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\Exporter;

it('can export to sql format', function () {
    createTestDatabase();
    seedTestData();

    Config::set('dead-drop.tables.users', [
        'columns' => ['id', 'name', 'email', 'created_at', 'updated_at'],
    ]);

    $exporter = resolve(Exporter::class);
    $path = sys_get_temp_dir().'/dead-drop-feature-test';

    $result = $exporter->exportTable('users', 'testing', $path);

    expect($result['table'])->toBe('users')
        ->and($result['records'])->toBe(5)
        ->and(File::exists($result['file']))->toBeTrue();

    File::deleteDirectory($path);
});
