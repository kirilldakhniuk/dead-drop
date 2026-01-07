<?php

namespace Tests;

use KirillDakhniuk\DeadDrop\DeadDropServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DeadDropServiceProvider::class,
        ];
    }
}
