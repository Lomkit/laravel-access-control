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
     * Configures the testing application's authentication for web requests.
     *
     * This method sets the 'auth.guards.web' configuration to use a session-based driver
     * with the default 'users' provider, ensuring that authentication behaves consistently
     * during tests.
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
     * Retrieve the package's service provider classes.
     *
     * This method returns an array of service provider class names that are registered
     * with the Laravel application.
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
     * Authenticates a test user.
     *
     * If no user instance is provided, a new user is created using the resolved authentication factory.
     * The method then sets the authenticated user for the test using the specified guard.
     *
     * @param mixed|null $user Optional user instance to authenticate. If null, a new user is created.
     * @param string $driver The authentication guard to use. Defaults to 'web'.
     *
     * @return $this The test case instance with the authenticated user.
     */
    protected function withAuthenticatedUser($user = null, string $driver = 'web')
    {
        return $this->actingAs($user ?? $this->resolveAuthFactoryClass()::new()->create(), $driver);
    }

    /**
     * Resolves the authentication factory class.
     *
     * This placeholder method currently returns null and is intended to be updated in the future to
     * determine the appropriate factory class for creating authenticated user instances.
     *
     * @return null
     */
    protected function resolveAuthFactoryClass()
    {
        return null;
    }

    /**
     * Asserts that the given response indicates an unauthorized action.
     *
     * This method verifies that the response has a 403 status code and a JSON payload containing
     * a message of "This action is unauthorized." It is intended for use in test cases validating
     * access control behaviors.
     *
     * @param mixed $response The HTTP response to evaluate.
     */
    protected function assertUnauthorizedResponse($response)
    {
        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }
}
