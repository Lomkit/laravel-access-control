<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class AmbiguousSearchIndexException extends LogicException
{
    public static function for(string $index, string $first, string $second): self
    {
        return new self(sprintf(
            'The [%s] and [%s] models are both searched in the [%s] index, so Access::guardSearch() cannot tell which control filters a search of it. Give each ControlledSearchable model its own index.',
            $first,
            $second,
            $index,
        ));
    }
}
