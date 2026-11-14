<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class UnsearchableBoundaryException extends LogicException
{
    public static function for(Control $control): self
    {
        return new self(sprintf(
            'The [%s] boundary of the [%s] control has no search form, so a controlled search of [%s] cannot keep the rows inside it. Return the search keys of the boundary from its searchKeys() method, declare it with Boundary::where(), or search with the database engine.',
            $control->searchRules()->boundary(),
            $control::class,
            $control->getModel(),
        ));
    }
}
