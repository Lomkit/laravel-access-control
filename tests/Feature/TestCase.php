<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Sets up the test environment before each test.
     *
     * This method calls the parent's setUp to initialize the test framework and then
     * configures an authenticated user for testing purposes.
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
