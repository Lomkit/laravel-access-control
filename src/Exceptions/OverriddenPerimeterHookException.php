<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;

final class OverriddenPerimeterHookException extends LogicException
{
    public static function for(Perimeter $perimeter, PerimeterHook $hook): self
    {
        return new self(sprintf(
            'The [%s] perimeter overrides %s(), so its [%s] callback would never decide anything. Write the rule once: remove ->%s(...) from the declaration, or remove the %s() override and keep the callback.',
            $perimeter::class,
            $hook->value,
            $hook->callback(),
            $hook->callback(),
            $hook->value,
        ));
    }
}
