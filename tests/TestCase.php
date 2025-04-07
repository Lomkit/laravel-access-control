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
     * Configure the testing environment with default authentication settings.
     *
     * This method sets the 'web' authentication guard to use the session driver and the 'users' provider,
     * ensuring that authentication is properly configured during tests.
     *
     * @param \Illuminate\Foundation\Application $app The Laravel application instance.
     *
     * @return void
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
     * Retrieve the package's service providers.
     *
     * This method returns an array of service provider class names to be registered with the
     * Laravel application during testing, ensuring that the package's functionality is properly bootstrapped.
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>> An array of package service provider class names.
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
     * If a user instance is provided, it will be used for authentication; otherwise, a new user is created using
     * the factory returned by resolveAuthFactoryClass(). The authentication is performed under the specified guard.
     *
     * @param mixed|null $user Optional user instance to authenticate. If null, a new user is generated.
     * @param string $driver The authentication guard to use (defaults to "web").
     * @return mixed The test instance with the authenticated user.
     */
    protected function withAuthenticatedUser($user = null, string $driver = 'web')
    {
        return $this->actingAs($user ?? $this->resolveAuthFactoryClass()::new()->create(), $driver);
    }

    /**
     * Resolves the authentication factory class.
     *
     * This placeholder method is intended to be overridden in child classes to provide
     * a specific authentication factory class. By default, it returns null.
     *
     * @return null
     */
    protected function resolveAuthFactoryClass()
    {
        return null;
    }

    /**
     * Assert that the response indicates unauthorized access.
     *
     * This method verifies that the HTTP response has a 403 status code and a JSON body
     * containing a "message" field with the value "This action is unauthorized."
     *
     * @param mixed $response The response object to validate.
     */
    protected function assertUnauthorizedResponse($response)
    {
        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }
}
