<?php

namespace Lomkit\Access\Tests;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Lomkit\Access\AccessServiceProvider;
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
     * Configures the test application's environment by setting the default web authentication guard.
     *
     * Sets the authentication guard for web users to use the session driver with the 'users' provider.
     */
    protected function defineEnvironment($app)
    {
        tap($app->make('config'), function (Repository $config) {
            $config->set('auth.guards.web', [
                'driver'   => 'session',
                'provider' => 'users',
            ]);
        });
    }

    /**
     * Returns the package's service provider classes.
     *
     * This method registers the service providers necessary for the package in the test environment.
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            AccessServiceProvider::class,
        ];
    }

    /**
     * Authenticates a user for testing.
     *
     * If no user instance is provided, a new user is created using the default authentication factory.
     *
     * @param mixed|null $user   The user to authenticate. If null, a new user is created.
     * @param string     $driver The authentication guard to use. Defaults to 'web'.
     *
     * @return mixed The result of the actingAs method, representing the authenticated application instance.
     */
    protected function withAuthenticatedUser($user = null, string $driver = 'web')
    {
        return $this->actingAs($user ?? $this->resolveAuthFactoryClass()::new()->create(), $driver);
    }

    /**
     * Retrieves the authentication factory class.
     *
     * This default implementation returns null, indicating that no custom
     * authentication factory is provided. Override this method in subclasses
     * to specify a particular factory class when needed.
     *
     * @return null
     */
    protected function resolveAuthFactoryClass()
    {
        return null;
    }

    /**
     * Asserts that the response indicates an unauthorized action.
     *
     * This method verifies that the provided response has a 403 HTTP status code and a JSON body
     * with a message stating "This action is unauthorized."
     *
     * @param mixed $response The HTTP response object to validate.
     */
    protected function assertUnauthorizedResponse($response)
    {
        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }
}
