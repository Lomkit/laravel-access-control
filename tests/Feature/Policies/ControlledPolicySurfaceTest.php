<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Auth\Access\Response;
use Lomkit\Access\Policies\AutomaticPolicy;
use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ControlledPolicySurfaceTest extends PackageTestCase
{
    private const array ABILITIES = ['create', 'delete', 'forceDelete', 'restore', 'update', 'view', 'viewAny'];

    private const array RESERVED = ['decideByControl', 'declaredControl'];

    #[Test]
    public function it_exposes_only_the_standard_abilities_as_public_methods(): void
    {
        $this->assertEqualsCanonicalizing(self::ABILITIES, $this->methods(ControlledPolicy::class, ReflectionMethod::IS_PUBLIC));
    }

    #[Test]
    public function it_answers_every_public_ability_with_a_response(): void
    {
        foreach ((new ReflectionClass(ControlledPolicy::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            $this->assertInstanceOf(ReflectionNamedType::class, $type, $method->getName());
            $this->assertSame(Response::class, $type->getName(), $method->getName());
            $this->assertFalse($type->allowsNull(), $method->getName());
        }
    }

    #[Test]
    public function it_reserves_only_compound_names_that_no_ability_takes(): void
    {
        $this->assertEqualsCanonicalizing(self::RESERVED, $this->methods(ControlledPolicy::class, ReflectionMethod::IS_PROTECTED));
        $this->assertTrue((new ReflectionMethod(ControlledPolicy::class, 'decideByControl'))->isFinal());
    }

    #[Test]
    public function it_gives_the_automatic_policy_the_same_surface(): void
    {
        $this->assertEqualsCanonicalizing(self::ABILITIES, $this->methods(AutomaticPolicy::class, ReflectionMethod::IS_PUBLIC));
        $this->assertEqualsCanonicalizing(self::RESERVED, $this->methods(AutomaticPolicy::class, ReflectionMethod::IS_PROTECTED));
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function methods(string $class, int $filter): array
    {
        $names = array_map(fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass($class))->getMethods($filter));

        return array_values(array_diff($names, ['__construct']));
    }
}
