<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;

final class QueryCallbackReturnedAnotherBuilderException extends LogicException
{
    public static function for(Perimeter $perimeter, PerimeterHook $hook, Model $model): self
    {
        return new self(sprintf(
            'The [%s] callback of the [%s] perimeter returned another builder for the [%s] model. Constrain the builder the callback receives and return nothing: a returned builder is never used.',
            $hook->callback(),
            $perimeter::class,
            $model::class,
        ));
    }
}
