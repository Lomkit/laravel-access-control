<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Levels;

final class RowContextInLevelsException extends LogicException
{
    public static function at(int $position, string $relation, string $column): self
    {
        return new self(sprintf(
            'Level #%d given to %s::of() is a ThroughParent through the [%s] relation decidedInRowContext(\'%s\'). A group keeps the level a reader reaches in one value of the row context and hides the lower levels in the others. Declare this ThroughParent outside Levels::of(), where it adds up with the other perimeters of the control.',
            $position,
            Levels::class,
            $relation,
            $column,
        ));
    }
}
