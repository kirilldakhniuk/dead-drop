<?php

use Illuminate\Support\Facades\File;
use KirillDakhniuk\DeadDrop\DeadDropServiceProvider;

test('config file can be published', function () {
    $configPath = config_path('dead-drop.php');

    if (File::exists($configPath)) {
        File::delete($configPath);
    }

    expect(File::exists($configPath))->toBeFalse();

    $this->artisan('vendor:publish', [
        '--tag' => 'dead-drop-config',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists($configPath))->toBeTrue();

    $content = File::get($configPath);
    expect($content)->toContain('Dead Drop Configuration')
        ->and($content)->toContain('output_path')
        ->and($content)->toContain('tables');

    File::delete($configPath);
});

test('config file can be published with config tag', function () {
    $configPath = config_path('dead-drop.php');

    if (File::exists($configPath)) {
        File::delete($configPath);
    }

    expect(File::exists($configPath))->toBeFalse();

    $this->artisan('vendor:publish', [
        '--tag' => 'config',
        '--provider' => DeadDropServiceProvider::class,
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists($configPath))->toBeTrue();

    File::delete($configPath);
});
