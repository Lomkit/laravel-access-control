<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class DuplicatePerimeterCallbackException extends LogicException
{
    public static function for(Perimeter $perimeter, string $callback): self
    {
        return new self(sprintf(
            'The [%s] perimeter declares %s() twice: the second declaration would replace the first, which would widen or change the rows the perimeter grants without a trace. Declare one %s() callback that checks both conditions, or, to narrow a perimeter class, give the class a wither that clones it and sets the variant its hooks read, such as ->ofKind(\'invoice\').',
            $perimeter::class,
            $callback,
            $callback,
        ));
    }

    public static function searchKeys(Perimeter $perimeter): self
    {
        return new self(sprintf(
            'The [%s] perimeter declares searchKeys() twice: the second declaration would replace the first keys and drop the reindexWhen() rules declared after them. Declare searchKeys() once, with a row closure and a reader closure that cover both, or split the rows between two perimeters with distinct key names.',
            $perimeter::class,
        ));
    }
}
