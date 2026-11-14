<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use ReflectionParameter;

final class AbilityParameterTypeException extends LogicException
{
    public static function forAllowedCallback(Perimeter $perimeter, ReflectionParameter $parameter): self
    {
        return new self(sprintf(
            'The [allowed] callback of the [%s] perimeter declares its second parameter as [%s], which cannot receive the ability. Type the second parameter Ability $ability, and compare it with $ability->is(\'update\').',
            $perimeter::class,
            self::describe($parameter),
        ));
    }

    public static function forHook(Perimeter $perimeter, ReflectionParameter $parameter): self
    {
        return new self(sprintf(
            'The [%s] perimeter overrides applyAllowedCallback() with its second parameter declared as [%s], which cannot receive the ability. Type the second parameter Ability $ability, and compare it with $ability->is(\'update\').',
            $perimeter::class,
            self::describe($parameter),
        ));
    }

    private static function describe(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        return $type === null
            ? "\${$parameter->getName()}"
            : "{$type} \${$parameter->getName()}";
    }
}
