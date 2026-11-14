<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;

final class MissingPerimeterCallbackException extends LogicException
{
    public static function for(Perimeter $perimeter, PerimeterHook $hook): self
    {
        return new self(sprintf(
            'The [%s] perimeter has no [%s] callback. Declare it with ->%s(...), or override %s() in the perimeter, so access is never granted by default.',
            $perimeter::class,
            $hook->callback(),
            $hook->callback(),
            $hook->value,
        ));
    }
}
