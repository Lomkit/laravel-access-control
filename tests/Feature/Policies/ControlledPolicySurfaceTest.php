<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Auth\Access\Response;
use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ControlledPolicySurfaceTest extends PackageTestCase
{
    #[Test]
    public function it_exposes_only_the_standard_abilities_as_public_methods(): void
    {
        $public = array_map(fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(ControlledPolicy::class))->getMethods(ReflectionMethod::IS_PUBLIC));
        sort($public);

        $this->assertSame(['create', 'delete', 'forceDelete', 'restore', 'update', 'view', 'viewAny'], $public);
    }

    #[Test]
    public function it_answers_every_public_ability_with_a_response(): void
    {
        foreach ((new ReflectionClass(ControlledPolicy::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            $this->assertInstanceOf(ReflectionNamedType::class, $type, $method->getName());
            $this->assertSame(Response::class, $type->getName(), $method->getName());
        }
    }

    #[Test]
    public function it_reserves_only_compound_names_that_no_ability_takes(): void
    {
        $protected = array_map(fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(ControlledPolicy::class))->getMethods(ReflectionMethod::IS_PROTECTED));
        sort($protected);

        $this->assertSame(['decideByControl', 'declaredControl'], $protected);
        $this->assertTrue((new ReflectionMethod(ControlledPolicy::class, 'decideByControl'))->isFinal());
    }
}
