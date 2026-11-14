<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Levels;

final class NestedLevelsException extends LogicException
{
    public static function at(int $position): self
    {
        return new self(sprintf(
            'Level #%d given to %s::of() is another Levels group. Each level of a group is a single perimeter: list the perimeters of the inner group in the outer one, widest first, or move the inner group out of it, where it adds up with the other perimeters of the control.',
            $position,
            Levels::class,
        ));
    }
}
