<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

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
}
