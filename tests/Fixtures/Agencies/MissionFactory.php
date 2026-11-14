<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mission> */
#[UseModel(Mission::class)]
class MissionFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'agency_id' => AgencyFactory::new(),
            'member_id' => MemberFactory::new(),
            'title' => 'Survey',
            'public' => false,
        ];
    }
}
