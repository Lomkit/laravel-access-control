<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnresolvablePivotException extends LogicException
{
    public static function for(string $pivot): self
    {
        return new self(sprintf(
            'The [%s] pivot uses ReindexesSearch but was written outside its relation, so it does not know the models it links. Write it through the relation (attach(), detach(), sync(), updateExistingPivot()), or call searchable() on the linked models yourself.',
            $pivot,
        ));
    }
}
