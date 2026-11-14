<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class MissingParentControlException extends LogicException
{
    public static function for(Control $control, string $relation, string $parent): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control inherits the access of the [%s] model through the [%s] relation, but no control is registered for that model, so no child row would ever be granted. Declare a control for the [%s] model with #[ControlledBy(...)] or "php artisan make:control".',
            $control::class,
            $parent,
            $relation,
            $parent,
        ));
    }
}
