<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class UnsearchableBoundaryException extends LogicException
{
    public static function for(Control $control): self
    {
        return new self(sprintf(
            'The [%s] control declares a boundary, and a Scout search cannot apply it: the search would return rows outside the boundary. Read the rows of [%s] through a controlled Eloquent query instead of a controlled search.',
            $control::class,
            $control->getModel(),
        ));
    }
}
