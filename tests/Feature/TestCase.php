<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Tests\Support\Database\Factories\UserFactory;
use Lomkit\Access\Tests\Support\Traits\InteractsWithAuthorization;
use Lomkit\Access\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use InteractsWithAuthorization;

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
