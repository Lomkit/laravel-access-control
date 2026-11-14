<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agency> */
#[UseModel(Agency::class)]
class AgencyFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => 'North',
        ];
    }
}
