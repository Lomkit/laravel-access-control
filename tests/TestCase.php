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
     * Configures the application's authentication guard.
     *
     * Retrieves the configuration repository from the provided application instance and updates the
     * "web" guard to use session-based authentication with the "users" provider.
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
     * Returns the service provider classes required by the package.
     *
     * This method registers the package's service providers with the Laravel application.
     *
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>> An array containing the service provider classes.
     */
    protected function getPackageProviders($app)
    {
        return [
            AccessServiceProvider::class,
        ];
    }

    /**
     * Authenticates a user for testing using the specified guard.
     *
     * If no user is provided, a new user instance is created via the authentication factory
     * returned by resolveAuthFactoryClass and then authenticated.
     *
     * @param mixed|null $user An existing user instance to authenticate, or null to auto-create one.
     * @param string $driver The authentication guard driver to use (defaults to 'web').
     * @return mixed The result of the authentication action.
     */
    protected function withAuthenticatedUser($user = null, string $driver = 'web')
    {
        return $this->actingAs($user ?? $this->resolveAuthFactoryClass()::new()->create(), $driver);
    }

    /**
     * Resolves the authentication factory class.
     *
     * By default, this method returns null, indicating that no custom factory is provided.
     * Override this method in a subclass to supply a custom authentication factory for user creation.
     *
     * @return null
     */
    protected function resolveAuthFactoryClass()
    {
        return null;
    }

    /**
     * Asserts that the given response represents an unauthorized access attempt.
     *
     * This method verifies that the response has a 403 HTTP status code and a JSON payload containing
     * the message "This action is unauthorized." It is used to validate proper access control in tests.
     *
     * @param mixed $response The response instance to validate.
     */
    protected function assertUnauthorizedResponse($response)
    {
        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }
}
