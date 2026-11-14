<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class InvalidControlModelException extends LogicException
{
    public static function missing(string $control): self
    {
        return new self(sprintf(
            'The [%s] control declares no model. Declare the model it controls as the default value of its property, such as protected string $model = Post::class;.',
            $control,
        ));
    }

    public static function notAClass(string $control, string $model): self
    {
        return new self(sprintf(
            'The [%s] control declares [%s] as its model, which is not a class.',
            $control,
            $model,
        ));
    }

    public static function notAModel(string $control, string $model): self
    {
        return new self(sprintf(
            'The [%s] control declares [%s] as its model, which is not an Eloquent model.',
            $control,
            $model,
        ));
    }
}
