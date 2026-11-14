<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class InvalidControlledByException extends LogicException
{
    public static function notAControl(string $model, mixed $control): self
    {
        return new self(sprintf(
            'The #[ControlledBy] attribute of the [%s] model names [%s], which is not a concrete control class.',
            $model,
            is_string($control) ? $control : get_debug_type($control),
        ));
    }

    public static function controlsAnotherModel(string $model, string $control, string $controlledModel): self
    {
        return new self(sprintf(
            'The #[ControlledBy] attribute of the [%s] model names the [%s] control, which controls the [%s] model. Name the control whose $model is [%s].',
            $model,
            $control,
            $controlledModel,
            $model,
        ));
    }
}
