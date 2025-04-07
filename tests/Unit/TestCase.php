<?php

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Initializes the test environment by setting up an authenticated user.
     *
     * This method overrides the parent's setUp routine, first invoking the parent's setup
     * and then calling withAuthenticatedUser() to ensure that tests run within an
     * authenticated user context.
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
