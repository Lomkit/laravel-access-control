<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;
use ReflectionParameter;

final class AbilityParameterTypeException extends LogicException
{
    public static function forCallback(Perimeter $perimeter, PerimeterHook $hook, ReflectionParameter $parameter): self
    {
        return new self(sprintf(
            'The [%s] callback of the [%s] perimeter declares its ability parameter as [%s], which cannot receive the ability. Type it Ability $ability, and compare it with $ability->is(\'update\').',
            $hook->callback(),
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
