<?php

namespace Lomkit\Access\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lomkit\Access\Tests\Support\Models\Model;

class ModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model|TModel>
     */
    protected $model = Model::class;

    /**
     * Returns the default attribute values for the model.
     *
     * This method generates an associative array representing the model's default state,
     * including a randomly generated name, a random number between -9999999 and 9999999,
     * and default boolean flags (is_shared, is_global, is_client, is_own) set to false.
     *
     * @return array The default attributes of the model.
     */
    public function definition()
    {
        return [
            'name'      => fake()->name(),
            'number'    => fake()->numberBetween(-9999999, 9999999),
            'is_shared' => false,
            'is_global' => false,
            'is_client' => false,
            'is_own'    => false,
        ];
    }
}
