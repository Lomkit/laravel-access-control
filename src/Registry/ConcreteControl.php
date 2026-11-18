<?php

namespace Lomkit\Access\Registry;

use Lomkit\Access\Controls\Control;
use ReflectionClass;

/** @internal */
final class ConcreteControl
{
    /** @phpstan-assert-if-true class-string<Control> $class */
    public static function is(string $class): bool
    {
        return is_subclass_of($class, Control::class)
            && (new ReflectionClass($class))->isInstantiable();
    }
}
