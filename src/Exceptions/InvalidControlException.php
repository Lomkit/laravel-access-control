<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class InvalidControlException extends LogicException
{
    public static function notAControl(string $control): self
    {
        return new self(sprintf(
            'The [%s] class is not a concrete control class.',
            $control,
        ));
    }

    public static function resolvedAs(string $control, mixed $instance): self
    {
        return new self(sprintf(
            'The container resolved the [%s] control as [%s], which does not extend it.',
            $control,
            get_debug_type($instance),
        ));
    }
}
