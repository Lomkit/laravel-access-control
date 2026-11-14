<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class MissingControlException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'No control is registered for the [%s] model. Declare its control on the model with #[ControlledBy(PostControl::class)], or create one with "php artisan make:control" in a directory the controls are discovered in.',
            $model,
        ));
    }
}
