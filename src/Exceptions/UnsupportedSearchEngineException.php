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

    public static function turbopuffer(Model $model): self
    {
        return new self(sprintf(
            'A controlled search of the [%s] model cannot run on the Turbopuffer engine yet: Scout filters an array attribute with In, and Turbopuffer needs ContainsAny to match one of the access keys. Use the database, Algolia, Meilisearch or Typesense engine.',
            $model::class,
        ));
    }

    public static function untrusted(Model $model, mixed $engine): self
    {
        $class = get_debug_type($engine);

        return new self(sprintf(
            'A controlled search of the [%1$s] model cannot run on the [%2$s] engine: the package only knows that the Algolia, Meilisearch and Typesense engines render whereIn on an array attribute as "at least one element in common", and an engine that ignores it would return every document. If [%2$s] filters that way, call Access::trustSearchEngine(%2$s::class) in a service provider.',
            $model::class,
            $class,
        ));
    }
}
