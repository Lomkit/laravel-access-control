<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Sets up the test case environment.
     *
     * Executes the parent setup routine and configures an authenticated user so that tests run with the necessary authentication context.
     *
     * @return void
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
