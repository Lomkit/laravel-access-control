<?php

namespace Lomkit\Access\Exceptions;

use InvalidArgumentException;

final class InvalidAbilityException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('An ability name cannot be empty. Name the ability, such as Ability::of(\'approve\').');
    }

    public static function malformed(string $name): self
    {
        return new self(sprintf(
            'The [%s] ability name is not valid UTF-8.',
            $name,
        ));
    }

    public static function withWhitespace(string $name): self
    {
        return new self(sprintf(
            'The [%s] ability name contains whitespace. Name an ability with a single word, such as Ability::of(\'approve\').',
            $name,
        ));
    }
}
