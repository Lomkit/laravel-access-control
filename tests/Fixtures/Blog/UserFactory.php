<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
#[UseModel(User::class)]
class UserFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'client_id' => ClientFactory::new(),
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'current_workspace_id' => 1,
        ];
    }
}
