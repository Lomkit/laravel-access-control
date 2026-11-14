<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class InvalidBoundaryValueException extends LogicException
{
    public static function for(Model $model, string $column, mixed $value): self
    {
        return new self(sprintf(
            'The boundary of the [%s] model on [%s] received a value of type [%s] from the reader. The reader value must be an int, a string, or null when the reader has no place inside the boundary.',
            $model::class,
            $column,
            get_debug_type($value),
        ));
    }
}
