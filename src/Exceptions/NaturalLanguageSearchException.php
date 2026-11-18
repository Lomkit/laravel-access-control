<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class NaturalLanguageSearchException extends LogicException
{
    public static function typesense(Model $model, string $source): self
    {
        return new self(sprintf(
            'The nl_query that %s gives to a controlled Typesense search of the [%s] model turns on natural-language search, which the package refuses: Typesense adds the filter and the parameters that its language model generates after the access filter, so they could widen what the reader finds. Search with q, filter_by and where() instead.',
            $source,
            $model::class,
        ));
    }
}
