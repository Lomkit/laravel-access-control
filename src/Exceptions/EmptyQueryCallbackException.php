<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

final class EmptyQueryCallbackException extends LogicException
{
    public static function for(Control $control, Perimeter $perimeter): self
    {
        return new self(sprintf(
            'The [query] callback of the [%s] perimeter in the [%s] control added no constraint to the query it receives, so it would not restrict any row. Declare ->unrestricted() instead of ->query(...) on a perimeter that reaches every row.',
            $perimeter::class,
            $control::class,
        ));
    }
}
