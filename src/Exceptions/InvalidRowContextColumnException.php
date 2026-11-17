<?php

namespace Lomkit\Access\Exceptions;

use InvalidArgumentException;

final class InvalidRowContextColumnException extends InvalidArgumentException
{
    public static function for(string $relation, string $column): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter through the [%s] relation is decidedInRowContext(\'%s\'), which is not a plain column name (letters, digits and underscores, not starting with a digit). Name the column of the child row that holds the value, such as decidedInRowContext(\'brand_id\'), without its table.',
            $relation,
            $column,
        ));
    }
}
