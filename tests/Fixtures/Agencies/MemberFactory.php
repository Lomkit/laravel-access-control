<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
#[UseModel(Member::class)]
class MemberFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'agency_id' => AgencyFactory::new(),
            'name' => 'Linus',
            'email' => 'linus@example.com',
        ];
    }
}
