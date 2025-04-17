<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\Support\Models\Client;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = UserFactory::new()
            ->for(Client::factory())
            ->createOne();
        $this->withAuthenticatedUser($user);
    }

    protected function resolveAuthFactoryClass()
    {
        return UserFactory::class;
    }
}
