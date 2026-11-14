<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class InvalidReindexAttributesException extends LogicException
{
    public static function empty(Perimeter $perimeter, string $model): self
    {
        return new self(sprintf(
            'The [%s] perimeter reindexes its rows when [%s] changes without naming an attribute. List the attributes its search keys read in changed:, such as changed: [\'client_id\'].',
            $perimeter::class,
            $model,
        ));
    }

    public static function blank(Perimeter $perimeter, string $model): self
    {
        return new self(sprintf(
            'The [%s] perimeter reindexes its rows when [%s] changes and lists a blank attribute name in changed:. Name each attribute its search keys read, such as changed: [\'client_id\'].',
            $perimeter::class,
            $model,
        ));
    }
}
