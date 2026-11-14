<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;

final class UnrestrictedPerimeterCallbackException extends LogicException
{
    public static function for(Perimeter $perimeter, string $callback): self
    {
        return new self(sprintf(
            'The [%s] perimeter is unrestricted and declares a [%s] callback. An unrestricted perimeter reaches every row: remove the [%s] callback, or remove ->unrestricted().',
            $perimeter::class,
            $callback,
            $callback,
        ));
    }

    public static function overriding(Perimeter $perimeter, PerimeterHook $hook): self
    {
        return new self(sprintf(
            'The [%s] perimeter is unrestricted and overrides %s(). An unrestricted perimeter reaches every row, so %s() would never run: remove the override, or remove ->unrestricted().',
            $perimeter::class,
            $hook->value,
            $hook->value,
        ));
    }
}
