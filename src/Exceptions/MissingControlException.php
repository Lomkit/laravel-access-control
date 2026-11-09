<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

class MissingControlException extends LogicException
{
    /**
     * Create the exception for a model that has no registered control.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    public static function forModel(string $model): static
    {
        return new static(sprintf(
            'No control is registered for the [%s] model. Create one with "php artisan make:control" or register it with Access::addControl().',
            $model,
        ));
    }
}
