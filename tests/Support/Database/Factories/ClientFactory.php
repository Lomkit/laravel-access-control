<?php

namespace Lomkit\Access\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Tests\Support\Models\Client;
use Lomkit\Access\Tests\Support\Models\User;

/**
 * @extends Factory<User>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model|TModel>
     */
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
