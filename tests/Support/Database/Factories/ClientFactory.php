<?php

namespace Lomkit\Access\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lomkit\Access\Tests\Support\Models\Client;
use Lomkit\Access\Tests\Support\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<User>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model|TModel>
     */
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
        ];
    }
}
