<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;

final class PerimeterCallbackResultException extends LogicException
{
    public static function notABoolean(Perimeter $perimeter, PerimeterHook $hook, mixed $result): self
    {
        return new self(sprintf(
            'The [%s] callback of the [%s] perimeter returned [%s] instead of a boolean. Return true or false, so access is never granted by a value that only looks true.',
            $hook->callback(),
            $perimeter::class,
            get_debug_type($result),
        ));
    }
}
