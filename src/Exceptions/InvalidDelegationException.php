<?php

namespace Lomkit\Access\Exceptions;

use InvalidArgumentException;

final class InvalidDelegationException extends InvalidArgumentException
{
    private const string EXAMPLE = "List the abilities, or map a child ability to a parent ability, such as ->delegates(['view', 'update', 'create' => 'view']).";

    public static function empty(string $relation): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter through the [%s] relation delegates no ability. %s',
            $relation,
            self::EXAMPLE,
        ));
    }

    public static function duplicate(string $relation, string $ability): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter through the [%s] relation delegates the [%s] ability twice. Keep a single entry for it. %s',
            $relation,
            $ability,
            self::EXAMPLE,
        ));
    }

    public static function notAnAbility(string $relation, int|string $key, mixed $value): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter through the [%s] relation delegates a [%s] at key [%s], not an ability name or an Ability. %s',
            $relation,
            get_debug_type($value),
            $key,
            self::EXAMPLE,
        ));
    }
}
