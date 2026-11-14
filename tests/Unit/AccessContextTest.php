<?php

namespace Lomkit\Access\Tests\Unit;

use Lomkit\Access\Ability;
use Lomkit\Access\Access;
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
    public function it_keeps_the_reader_and_the_decision_context_for_another_ability(): void
    {
        $ada = new User(['name' => 'Ada']);
        Access::decisionContextUsing(fn (): array => ['tenant' => 7]);
        $context = AccessContext::of($ada, Ability::create());
        Access::decisionContextUsing(fn (): array => ['tenant' => 8]);

        $parent = $context->withAbility(Ability::view());

        $this->assertSame($ada, $parent->reader());
        $this->assertTrue($parent->ability()->is('view'));
        $this->assertSame(['tenant' => 7], $parent->decisionContext());
        $this->assertTrue($context->ability()->is('create'));
    }

    #[Test]
    public function it_is_a_final_immutable_object_that_only_the_package_builds(): void
    {
        $class = new ReflectionClass(AccessContext::class);

        $this->assertTrue($class->isFinal());
        $this->assertTrue($class->isReadOnly());
        $this->assertFalse($class->isInstantiable());
        $this->assertSame(['of', 'withAbility', 'reader', 'ability', 'decisionContext'], array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
        ));
    }
}
