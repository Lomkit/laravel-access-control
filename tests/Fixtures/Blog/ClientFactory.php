<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
#[UseModel(Client::class)]
class ClientFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => 'Acme',
        ];
    }
}
