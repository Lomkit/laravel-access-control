<?php

namespace Lomkit\Access\Tests\Unit\Perimeters;

use Carbon\CarbonImmutable;
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Perimeters\PerimeterState;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Constructed\RuledPerimeter;
use Lomkit\Access\Tests\Fixtures\Purity\LazyValue;
use Lomkit\Access\Tests\Fixtures\Purity\LinkedRule;
use Lomkit\Access\Tests\Fixtures\Purity\NamedSecret;
use Lomkit\Access\Tests\Fixtures\Purity\Threshold;
use Lomkit\Access\Tests\Fixtures\Purity\TitleRule;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ClosureShapeTest extends PackageTestCase
{
    #[Test]
    public function it_compares_readonly_objects_holding_closures_property_by_property(): void
    {
        $this->assertTrue(ClosureShape::sameValues([TitleRule::exactly('Draft')], [TitleRule::exactly('Draft')]));
        $this->assertTrue(ClosureShape::sameValues([Ability::of('approve')], [Ability::of('approve')]));
        $this->assertFalse(ClosureShape::sameValues([TitleRule::exactly('Draft')], [TitleRule::exactly('Roadmap')]));
        $this->assertFalse(ClosureShape::sameValues([TitleRule::exactly('Draft')], [TitleRule::startingWith('Draft')]));
    }

    #[Test]
    public function it_compares_the_scalars_of_a_readonly_object_by_value_and_type(): void
    {
        $this->assertTrue(ClosureShape::sameValues([new Threshold(1)], [new Threshold(1)]));
        $this->assertFalse(ClosureShape::sameValues([new Threshold('1')], [new Threshold(1)]));
        $this->assertFalse(ClosureShape::sameValues([new Threshold(null)], [new Threshold(false)]));
    }

    #[Test]
    public function it_compares_a_model_inside_a_readonly_object_by_its_key(): void
    {
        $post = (new Post)->forceFill(['id' => 7, 'title' => 'Draft']);
        $reloaded = (new Post)->forceFill(['id' => 7, 'title' => 'Renamed']);
        $other = (new Post)->forceFill(['id' => 8, 'title' => 'Draft']);

        $this->assertTrue(ClosureShape::sameValues([new Threshold(1, $post)], [new Threshold(1, $reloaded)]));
        $this->assertFalse(ClosureShape::sameValues([new Threshold(1, $post)], [new Threshold(1, $other)]));
    }

    #[Test]
    public function it_compares_dates_by_their_instant(): void
    {
        $this->assertTrue(ClosureShape::sameValues([new Threshold(CarbonImmutable::parse('2024-01-01'))], [new Threshold(CarbonImmutable::parse('2024-01-01'))]));
        $this->assertFalse(ClosureShape::sameValues([new Threshold(CarbonImmutable::parse('2024-01-01 00:00:00.000001'))], [new Threshold(CarbonImmutable::parse('2024-01-01'))]));

        $from = CarbonImmutable::parse('2024-01-01');
        $later = CarbonImmutable::parse('2024-01-02');

        $this->assertFalse(ClosureShape::of(fn (): CarbonImmutable => $from)->matches(ClosureShape::of(fn (): CarbonImmutable => $later)));
    }

    #[Test]
    public function it_compares_nested_readonly_objects_at_any_depth_and_stops_on_a_cycle(): void
    {
        $this->assertTrue(ClosureShape::sameValues([new LinkedRule('root')], [new LinkedRule('root')]));
        $this->assertFalse(ClosureShape::sameValues([new LinkedRule('root', new LinkedRule('a'))], [new LinkedRule('root', new LinkedRule('b'))]));
        $this->assertFalse(ClosureShape::sameValues(
            [new LinkedRule('root', new LinkedRule('one', new LinkedRule('two', new LinkedRule('three', new LinkedRule('a')))))],
            [new LinkedRule('root', new LinkedRule('one', new LinkedRule('two', new LinkedRule('three', new LinkedRule('b')))))],
        ));
    }

    #[Test]
    public function it_compares_the_private_properties_a_readonly_object_inherits(): void
    {
        $this->assertTrue(ClosureShape::sameValues([new NamedSecret('s', 'rule')], [new NamedSecret('s', 'rule')]));
        $this->assertFalse(ClosureShape::sameValues([new NamedSecret('s', 'rule')], [new NamedSecret('t', 'rule')]));
    }

    #[Test]
    public function it_tells_an_uninitialized_property_from_an_initialized_one(): void
    {
        $this->assertTrue(ClosureShape::sameValues([new LazyValue(null)], [new LazyValue(null)]));
        $this->assertFalse(ClosureShape::sameValues([new LazyValue(null)], [new LazyValue('a')]));
    }

    #[Test]
    public function it_compares_a_readonly_object_held_by_a_constructed_perimeter(): void
    {
        $this->assertTrue(PerimeterState::of(new RuledPerimeter(TitleRule::exactly('Draft')))->matches(PerimeterState::of(new RuledPerimeter(TitleRule::exactly('Draft')))));
        $this->assertFalse(PerimeterState::of(new RuledPerimeter(TitleRule::exactly('Draft')))->matches(PerimeterState::of(new RuledPerimeter(TitleRule::exactly('Roadmap')))));
    }
}
