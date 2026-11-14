<?php

namespace Lomkit\Access\Tests\Feature\Fixtures;

use Lomkit\Access\Tests\Fixtures\Agencies\Agencies;
use Lomkit\Access\Tests\Fixtures\Agencies\Agency;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AgenciesFixtureTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Agencies];
    }

    #[Test]
    public function it_loads_the_members_of_an_agency(): void
    {
        $north = Agency::factory()->createOne(['name' => 'North']);
        $south = Agency::factory()->createOne(['name' => 'South']);
        Member::factory()->for($north)->createOne(['name' => 'Linus', 'email' => 'linus@example.com']);
        Member::factory()->for($north)->createOne(['name' => 'Margaret', 'email' => 'margaret@example.com']);
        Member::factory()->for($south)->createOne(['name' => 'Ken', 'email' => 'ken@example.com']);

        $loadedAgency = Agency::query()->with('members.agency')->findOrFail($north->getKey());

        $this->assertSame(['Linus', 'Margaret'], $loadedAgency->members->sortBy('name')->pluck('name')->values()->all());
        $this->assertSame(['North', 'North'], $loadedAgency->members->pluck('agency.name')->all());
    }
}
