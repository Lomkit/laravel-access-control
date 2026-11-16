<?php

namespace Lomkit\Access\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Access\Access;
use Lomkit\Access\AccessServiceProvider;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Tests\Fixtures\Fixture;
use Orchestra\Testbench\Attributes\ResetRefreshDatabaseState;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;

#[ResetRefreshDatabaseState]
#[WithConfig('scout.driver', 'collection')]
abstract class PackageTestCase extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventAccessingMissingAttributes();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    protected function forgetDiscoveredControls(): void
    {
        $this->app->forgetInstance(ControlDiscovery::class);
        $this->app->forgetInstance(ControlRegistry::class);
        Access::clearResolvedInstance(ControlRegistry::class);
    }

    /** @return list<Fixture> */
    protected function fixtures(): array
    {
        return [];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [AccessServiceProvider::class];
    }

    #[\Override]
    protected function defineDatabaseMigrations(): void
    {
        foreach ($this->fixtures() as $fixture) {
            $this->loadMigrationsFrom($fixture->migrations());
        }
    }
}
