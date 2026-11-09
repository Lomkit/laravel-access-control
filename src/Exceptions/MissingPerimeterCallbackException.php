<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

/** @phpstan-consistent-constructor */
class MissingPerimeterCallbackException extends LogicException
{
    /**
     * Create the exception for a callback the perimeter needs but does not declare.
     */
    public static function for(Perimeter $perimeter, string $callback): static
    {
        return new static(sprintf(
            'The [%s] perimeter has no [%s] callback. Declare it with ->%s(...) so access is never granted by default.',
            $perimeter::class,
            $callback,
            $callback,
        ));
    }
}
