<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class UnsupportedSearchEngineException extends LogicException
{
    public static function collection(Model $model): self
    {
        return new self(sprintf(
            'A controlled search of the [%s] model cannot run on the collection engine: it paginates before it filters, so its pages and totals would be wrong. Use the database engine.',
            $model::class,
        ));
    }

    public static function untrusted(Model $model, mixed $engine): self
    {
        $class = get_debug_type($engine);

        return new self(sprintf(
            'A controlled search of the [%1$s] model cannot run on the [%2$s] engine: the package only knows that the Algolia, Meilisearch and Typesense engines render whereIn on an array attribute as "at least one element in common" (it filters Turbopuffer with ContainsAny), and an engine that ignores whereIn would return every document. If [%2$s] filters that way, call Access::trustSearchEngine(%2$s::class) in a service provider.',
            $model::class,
            $class,
        ));
    }
}
