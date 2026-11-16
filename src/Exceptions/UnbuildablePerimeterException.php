<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnbuildablePerimeterException extends LogicException
{
    /** @param  list<string>  $arguments */
    public static function requiresArguments(string $perimeter, array $arguments): self
    {
        return new self(sprintf(
            'The constructor of the [%1$s] perimeter requires the [%2$s] arguments, so %1$s::new() cannot build it. Build it through a named static constructor of the class, or with new %1$s(...).',
            $perimeter,
            implode(', ', $arguments),
        ));
    }

    public static function notPublic(string $perimeter): self
    {
        return new self(sprintf(
            'The constructor of the [%1$s] perimeter is not public, so %1$s::new() cannot build it. Build it through a named static constructor of the class.',
            $perimeter,
        ));
    }

    public static function abstract(string $perimeter): self
    {
        return new self(sprintf(
            'The [%1$s] perimeter is abstract, so %1$s::new() cannot build it. Build one of its concrete subclasses.',
            $perimeter,
        ));
    }
}
