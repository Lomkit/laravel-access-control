<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class InvalidSearchKeyNameException extends LogicException
{
    private const string RULE = 'A search key name is made of lowercase letters, digits and underscores, such as [client] or [shared_with].';

    public static function named(string $name): self
    {
        return new self(sprintf('The search key name [%s] is invalid. %s', $name, self::RULE));
    }

    public static function forPerimeter(Perimeter $perimeter, string $name): self
    {
        return new self(sprintf('The [%s] perimeter declares search keys with the invalid name [%s]. %s', $perimeter::class, $name, self::RULE));
    }
}
