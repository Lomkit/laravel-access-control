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

    public static function forPivot(string $pivot, string $parent, string $related): self
    {
        return new self(sprintf(
            'The [%s] pivot uses ReindexesSearch, but neither [%s] nor [%s] uses the ControlledSearchable trait, so it has no document to reindex. Remove ReindexesSearch from the pivot, or use ControlledSearchable on the searchable model.',
            $pivot,
            $parent,
            $related,
        ));
    }

    public static function forReindex(string $model): self
    {
        return new self(sprintf(
            'The [%s] model does not use the ControlledSearchable trait, so its documents cannot be reindexed with their access keys. Use Lomkit\Access\Search\ControlledSearchable on the model.',
            $model,
        ));
    }
}
