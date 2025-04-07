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
