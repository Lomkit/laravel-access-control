<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('scout.driver', 'memory')]
#[WithConfig('scout.after_commit', true)]
abstract class SearchCheckTestCase extends CheckTestCase
{
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ScoutServiceProvider::class];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(EngineManager::class)->extend('memory', fn (): MemoryEngine => new MemoryEngine);
    }
}
