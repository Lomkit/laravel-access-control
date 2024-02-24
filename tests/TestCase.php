<?php

namespace Lomkit\Access\Tests;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Lomkit\Access\AccessServiceProvider;
use Lomkit\Access\Perimeters\Perimeters;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SitePerimeter;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Refresh a conventional test database.
     *
     * @return void
     */
    protected function refreshTestDatabase()
    {
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate', ['--path' => __DIR__.'/Support/Database/migrations', '--realpath' => true]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Refresh the in-memory database.
     *
     * @return void
     */
    protected function refreshInMemoryDatabase()
    {
        $this->artisan('migrate', ['--path' => __DIR__.'/Support/Database/migrations', '--realpath' => true]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function defineEnvironment($app)
    {
        foreach (
            [
                SharedPerimeter::class,
                ClientPerimeter::class,
                OwnPerimeter::class,
                SitePerimeter::class,
            ]
            as $perimeter
        ) {
            app(Perimeters::class)
                ->addPerimeter(new $perimeter());
        }

        tap($app->make('config'), function (Repository $config) {
            $config->set('auth.guards.web', [
                'driver'   => 'session',
                'provider' => 'users',
            ]);

            $config->set('access-control.perimeters.path', __DIR__);
        });
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            AccessServiceProvider::class,
        ];
    }
}
