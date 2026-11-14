<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class InvalidSearchKeyValueException extends LogicException
{
    private const string RULE = 'A search key callback returns an int, a string, a backed enum, null, a list of them, or a list of tuples such as [[\'fr\', 7]].';

    public static function for(string $name, Model $source, mixed $value): self
    {
        return new self(sprintf(
            'The [%s] search keys received a value of type [%s] for the [%s] model. %s',
            $name,
            get_debug_type($value),
            $source::class,
            self::RULE,
        ));
    }

    public static function emptyTuple(string $name, Model $source): self
    {
        return new self(sprintf('The [%s] search keys received an empty tuple for the [%s] model. %s', $name, $source::class, self::RULE));
    }
}
