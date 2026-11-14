<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class UndeclaredSearchScopeException extends LogicException
{
    public static function for(Model $model): self
    {
        return new self(sprintf(
            'The search of the [%s] model declares neither controlled() nor uncontrolled(). Add controlled() to keep the rows the reader may view, or uncontrolled() to search every row on purpose.',
            $model::class,
        ));
    }
}
