<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class UncombinableSearchFilterException extends LogicException
{
    public static function notAString(Model $model, string $source, mixed $filter): self
    {
        return new self(sprintf(
            'The filter_by that %s gives to a controlled Typesense search of the [%s] model is [%s], not a string, so the package cannot AND it with the access filter. Pass a Typesense filter expression as a string, or leave filter_by out.',
            $source,
            $model::class,
            get_debug_type($filter),
        ));
    }

    public static function notSelfContained(Model $model, string $source, string $filter): self
    {
        return new self(sprintf(
            'The filter_by [%s] that %s gives to a controlled Typesense search of the [%s] model is not a self-contained filter: outside quoted values, a parenthesis closes before it opens, or a parenthesis or a backtick stays open. The package ANDs filter_by with the access filter, so it must be a complete expression on its own: close every parenthesis and every backtick.',
            $filter,
            $source,
            $model::class,
        ));
    }
}
