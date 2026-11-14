<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class MissingControlledReaderException extends LogicException
{
    public static function for(Model $model): self
    {
        return new self(sprintf(
            'withAbilities() on a query of the [%s] model has no reader to decide for. Call controlled() or controlledFor($reader) before withAbilities().',
            $model::class,
        ));
    }
}
