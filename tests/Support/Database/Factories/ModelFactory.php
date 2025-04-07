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
     * Returns the default state for the model.
     *
     * This method returns an associative array containing default attributes for a model instance.
     * The returned array includes a randomly generated name, a random number between -9999999 and 9999999,
     * and boolean flags for shared, global, client, and own states, all set to false.
     *
     * @return array An associative array representing the model's default attributes.
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
