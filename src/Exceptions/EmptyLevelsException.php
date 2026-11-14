<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Levels;

final class EmptyLevelsException extends LogicException
{
    public static function make(): self
    {
        return new self(sprintf(
            '%s::of() received no level. A group lists its levels widest first, such as Levels::of(GlobalPerimeter::new()->...->unrestricted(), OwnPerimeter::new()->...), and only the highest level the reader reaches applies.',
            Levels::class,
        ));
    }
}
