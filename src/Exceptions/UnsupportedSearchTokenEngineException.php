<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class UnsupportedSearchTokenEngineException extends LogicException
{
    public static function for(Model $model, mixed $engine): self
    {
        return new self(sprintf(
            'The [%s] model searches with the [%s] engine, which has no browser search token: the package signs them for Meilisearch, Typesense and Algolia. Search on the server with %s::search()->controlled() instead.',
            $model::class,
            get_debug_type($engine),
            class_basename($model),
        ));
    }
}
