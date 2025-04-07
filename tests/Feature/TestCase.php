<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Set up the test environment with an authenticated user.
     *
     * Calls the parent's setUp() method and then configures an authenticated user for tests
     * that require a user context.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withAuthenticatedUser();
    }

    protected function resolveAuthFactoryClass()
    {
        return UserFactory::class;
    }
}
