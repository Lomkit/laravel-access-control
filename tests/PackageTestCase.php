<?php

namespace Lomkit\Access\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lomkit\Access\Access;
use Lomkit\Access\AccessServiceProvider;
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
        Access::flush();

        Access::$controlDiscoveryPaths = [];

        parent::setUp();

        Model::preventAccessingMissingAttributes();

        foreach ($this->fixtures() as $fixture) {
            foreach ($fixture->controls() as $control) {
                (new Access)->addControl(new $control);
            }
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();

        Access::flush();

        Access::$controlDiscoveryPaths = null;
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
