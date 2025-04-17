<?php

namespace Lomkit\Access\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;
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
        ];
    }

    public function clientPerimeter(): Factory
    {
        return $this->for(Auth::user()->client);
    }

    public function sharedPerimeter(): Factory
    {
        return $this->afterCreating(function (Model $model) {
            $model->sharedWithUsers()->attach(Auth::user());
        });
    }

    public function ownPerimeter(): Factory
    {
        return $this->for(Auth::user(), 'author');
    }
}
