<?php

namespace Lomkit\Access\Tests\Unit;

use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

class AccessContextTest extends PackageTestCase
{
    #[Test]
    public function it_holds_the_reader_and_the_ability(): void
    {
        $ada = new User(['name' => 'Ada']);

        $context = AccessContext::of($ada, Ability::update());

        $this->assertSame($ada, $context->reader());
        $this->assertTrue($context->ability()->is('update'));
    }

    #[Test]
    public function it_is_a_final_immutable_object_that_only_the_package_builds(): void
    {
        $class = new ReflectionClass(AccessContext::class);

        $this->assertTrue($class->isFinal());
        $this->assertTrue($class->isReadOnly());
        $this->assertFalse($class->isInstantiable());
        $this->assertSame(['of', 'reader', 'ability', 'decisionContext'], array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
        ));
    }
}
