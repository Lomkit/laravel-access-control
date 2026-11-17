<?php

namespace Lomkit\Access\Exceptions;

use UnexpectedValueException;

final class InvalidRowContextValuesException extends UnexpectedValueException
{
    public static function notAList(mixed $values): self
    {
        return new self(sprintf(
            'The values closure given to Access::rowContextUsing() returned a value of type [%s]. Return the list of the values the request covers, such as its brand ids: [1, 2], or [] when it covers none.',
            is_array($values) ? 'array with keys' : get_debug_type($values),
        ));
    }

    public static function notAValue(int $position, mixed $value): self
    {
        return new self(sprintf(
            'The values closure given to Access::rowContextUsing() returned a value of type [%s] at position %d. Each value must be an int or a non-empty string, as stored in the column of the child rows: return the identifier of a model instead of the model.',
            get_debug_type($value),
            $position,
        ));
    }
}
