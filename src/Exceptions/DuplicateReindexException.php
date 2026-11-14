<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class DuplicateReindexException extends LogicException
{
    public static function for(Perimeter $perimeter, string $model, string $declared): self
    {
        if ($model === $declared) {
            return new self(sprintf(
                'The [%s] perimeter declares reindexWhen() twice for [%s]. Declare it once for that model and list every attribute its search keys read in changed:.',
                $perimeter::class,
                $model,
            ));
        }

        return new self(sprintf(
            'The [%s] perimeter declares reindexWhen() for [%s] and for [%s], one of which extends the other, so a change of one row would match both. Declare it once, for the parent class, and list every attribute its search keys read in changed:.',
            $perimeter::class,
            $declared,
            $model,
        ));
    }
}
