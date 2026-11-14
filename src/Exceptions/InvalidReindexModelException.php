<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class InvalidReindexModelException extends LogicException
{
    public static function for(Perimeter $perimeter, string $model): self
    {
        return new self(sprintf(
            'The [%s] perimeter reindexes its rows when [%s] changes, which is not an Eloquent model class. Pass the class of the model the search keys read, such as reindexWhen(Project::class, ...).',
            $perimeter::class,
            $model,
        ));
    }
}
