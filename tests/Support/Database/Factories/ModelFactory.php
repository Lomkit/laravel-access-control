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
     * Returns the default attribute values for a model instance.
     *
     * This method provides an associative array of attributes with a randomly generated name,
     * a random number between -9999999 and 9999999, and a set of boolean flags ('is_shared',
     * 'is_global', 'is_client', 'is_own') all set to false. These defaults are intended for use
     * in testing scenarios to instantiate models with baseline values.
     *
     * @return array The default attributes for the model.
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
