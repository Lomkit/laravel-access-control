<?php

namespace Lomkit\Access\Exceptions;

use UnexpectedValueException;

final class InvalidDecisionContextException extends UnexpectedValueException
{
    public static function notAnArray(mixed $values): self
    {
        return new self(sprintf(
            'The resolver given to Access::decisionContextUsing() returned a value of type [%s]. Return an array of scalar values keyed by name, such as [\'brand\' => $brand?->id].',
            get_debug_type($values),
        ));
    }

    public static function unnamed(int $key): self
    {
        return new self(sprintf(
            'The resolver given to Access::decisionContextUsing() returned a value under the integer key [%d]. Key each value by a name that is not a number, such as [\'brand\' => $brand?->id].',
            $key,
        ));
    }

    public static function notScalar(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The resolver given to Access::decisionContextUsing() returned a value of type [%s] under the [%s] key. Each value must be a string, an int, a bool or null: return the identifier of a model instead of the model.',
            get_debug_type($value),
            $key,
        ));
    }
}
