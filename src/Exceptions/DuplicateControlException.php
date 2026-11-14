<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class DuplicateControlException extends LogicException
{
    public static function forModel(string $model, string $control, string $otherControl): self
    {
        return new self(sprintf(
            'The [%s] model has two controls, [%s] and [%s]. A model has a single control: remove one of them, or point its $model at another model.',
            $model,
            $control,
            $otherControl,
        ));
    }
}
