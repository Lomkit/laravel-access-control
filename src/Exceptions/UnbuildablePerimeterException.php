<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnbuildablePerimeterException extends LogicException
{
    public static function for(string $perimeter): self
    {
        return new self(sprintf(
            'The constructor of the [%1$s] perimeter requires arguments or is not public, so %1$s::new() cannot build it. Build it with a named static constructor of the class, such as %1$s::reading(...), or with new %1$s(...) when its constructor is public.',
            $perimeter,
        ));
    }
}
