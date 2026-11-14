<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

final class QueryCallbackReturnedAnotherBuilderException extends LogicException
{
    public static function for(Control $control, Perimeter $perimeter, string $callback): self
    {
        return new self(sprintf(
            'The [%s] callback of the [%s] perimeter in the [%s] control returned another builder. Constrain the builder the callback receives and return nothing: a returned builder is never used.',
            $callback,
            $perimeter::class,
            $control::class,
        ));
    }
}
