<?php

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
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
