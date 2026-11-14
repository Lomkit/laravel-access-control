<?php

namespace Lomkit\Access\Tests\Unit;

use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Stringable;

class AbilityTest extends PackageTestCase
{
    /** @return array<string, array{Ability, string}> */
    public static function standardAbilities(): array
    {
        return [
            'view' => [Ability::view(), 'view'],
            'create' => [Ability::create(), 'create'],
            'update' => [Ability::update(), 'update'],
            'delete' => [Ability::delete(), 'delete'],
            'restore' => [Ability::restore(), 'restore'],
            'forceDelete' => [Ability::forceDelete(), 'forceDelete'],
        ];
    }

    #[Test]
    #[DataProvider('standardAbilities')]
    public function it_names_each_standard_ability(Ability $ability, string $name): void
    {
        $this->assertSame($name, $ability->name());
        $this->assertSame($name, (string) $ability);
        $this->assertTrue($ability->is(Ability::of($name)));
    }

    #[Test]
    public function it_compares_abilities_by_name(): void
    {
        $this->assertTrue(Ability::view()->is(Ability::view()));
        $this->assertTrue(Ability::of('approve')->is(Ability::of('approve')));
        $this->assertFalse(Ability::view()->is(Ability::update()));
        $this->assertFalse(Ability::of('approve')->is(Ability::of('archive')));
    }

    #[Test]
    public function it_compares_an_ability_with_a_name(): void
    {
        $this->assertTrue(Ability::view()->is('view'));
        $this->assertTrue(Ability::of('approve')->is('approve'));
        $this->assertFalse(Ability::view()->is('viewAny'));
        $this->assertFalse(Ability::forceDelete()->is('forcedelete'));
        $this->assertFalse(Ability::view()->is(''));
    }

    #[Test]
    public function it_keeps_a_business_ability_open(): void
    {
        $ability = Ability::of('approve');

        $this->assertSame('approve', $ability->name());
        $this->assertSame('approve', (string) $ability);
    }

    #[Test]
    public function it_converts_to_its_name_in_a_string(): void
    {
        $ability = Ability::of('publish');

        $this->assertInstanceOf(Stringable::class, $ability);
        $this->assertSame('publish client posts', "{$ability} client posts");
        $this->assertSame('publish client posts', sprintf('%s client posts', $ability));
    }

    #[Test]
    public function it_keeps_its_name_through_serialization(): void
    {
        $restored = unserialize(serialize(Ability::of('approve')));

        $this->assertInstanceOf(Ability::class, $restored);
        $this->assertTrue($restored->is(Ability::of('approve')));
        $this->assertTrue(Ability::of('approve')->is($restored));
        $this->assertFalse($restored->is(Ability::view()));
        $this->assertSame('approve', $restored->name());
    }

    #[Test]
    public function it_is_an_immutable_final_class(): void
    {
        $class = new ReflectionClass(Ability::class);

        $this->assertTrue($class->isFinal());
        $this->assertTrue($class->isReadOnly());
        $this->assertFalse($class->getConstructor()?->isPublic());
    }

    #[Test]
    public function it_refuses_an_empty_name(): void
    {
        $this->expectException(InvalidAbilityException::class);
        $this->expectExceptionMessage('An ability name cannot be empty.');

        Ability::of('');
    }

    /** @return array<string, array{string}> */
    public static function namesWithWhitespace(): array
    {
        return [
            'a space' => ['approve post'],
            'a leading space' => [' approve'],
            'a trailing space' => ['approve '],
            'only spaces' => ['   '],
            'a tab' => ["approve\tpost"],
            'a new line' => ["approve\n"],
            'a non-breaking space' => ["approve\u{00A0}post"],
        ];
    }

    #[Test]
    #[DataProvider('namesWithWhitespace')]
    public function it_refuses_a_name_with_whitespace(string $name): void
    {
        $this->expectException(InvalidAbilityException::class);
        $this->expectExceptionMessage(sprintf('The [%s] ability name contains whitespace.', $name));

        Ability::of($name);
    }

    #[Test]
    public function it_refuses_a_name_that_is_not_valid_utf8(): void
    {
        $this->expectException(InvalidAbilityException::class);
        $this->expectExceptionMessage('ability name is not valid UTF-8.');

        Ability::of("approve\xC3");
    }
}
