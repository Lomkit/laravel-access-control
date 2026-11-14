<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class ReindexWithoutSearchKeysException extends LogicException
{
    public static function for(Perimeter $perimeter, string $model): self
    {
        return new self(sprintf(
            'The [%s] perimeter reindexes its rows when [%s] changes but declares no search keys, so nothing in its documents depends on it. Declare searchKeys() before reindexWhen().',
            $perimeter::class,
            $model,
        ));
    }
}
