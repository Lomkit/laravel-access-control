<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class MissingControlledSearchableException extends LogicException
{
    public static function for(Model $model): self
    {
        return new self(sprintf(
            'The [%s] model uses Laravel Scout\'s Searchable trait, so its documents carry no access keys. Replace Searchable with Lomkit\Access\Search\ControlledSearchable, then reindex the model.',
            $model::class,
        ));
    }
}
