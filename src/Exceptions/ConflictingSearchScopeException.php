<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ConflictingSearchScopeException extends LogicException
{
    public static function for(Model $model): self
    {
        return new self(sprintf(
            'The search of the [%s] model declares both controlled() and uncontrolled(). Keep the one the search means.',
            $model::class,
        ));
    }
}
