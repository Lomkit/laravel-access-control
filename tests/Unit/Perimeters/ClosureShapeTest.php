<?php

namespace Lomkit\Access\Tests\Unit\Perimeters;

use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Tests\Fixtures\Purity\LinkedRule;
use Lomkit\Access\Tests\Fixtures\Purity\TitleRule;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ClosureShapeTest extends PackageTestCase
{
    #[Test]
    public function it_compares_readonly_objects_property_by_property(): void
    {
        $this->assertTrue(ClosureShape::sameValues([TitleRule::exactly('Draft')], [TitleRule::exactly('Draft')]));
        $this->assertTrue(ClosureShape::sameValues([Ability::of('approve')], [Ability::of('approve')]));
        $this->assertFalse(ClosureShape::sameValues([TitleRule::exactly('Draft')], [TitleRule::exactly('Roadmap')]));
        $this->assertFalse(ClosureShape::sameValues([TitleRule::exactly('1')], [TitleRule::exactly(1)]));
        $this->assertFalse(ClosureShape::sameValues([TitleRule::exactly(null)], [TitleRule::exactly(false)]));
        $this->assertFalse(ClosureShape::sameValues([TitleRule::exactly('Draft')], [TitleRule::startingWith('Draft')]));
    }

    #[Test]
    public function it_stops_comparing_readonly_objects_beyond_the_nesting_limit(): void
    {
        $this->assertTrue(ClosureShape::sameValues([new LinkedRule('root')], [new LinkedRule('root')]));
        $this->assertFalse(ClosureShape::sameValues([new LinkedRule('root', new LinkedRule('a'))], [new LinkedRule('root', new LinkedRule('b'))]));
        $this->assertTrue(ClosureShape::sameValues(
            [new LinkedRule('root', new LinkedRule('middle', new LinkedRule('a')))],
            [new LinkedRule('root', new LinkedRule('middle', new LinkedRule('b')))],
        ));
    }
}
