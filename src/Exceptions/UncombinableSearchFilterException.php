<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class UncombinableSearchFilterException extends LogicException
{
    public static function notAString(Model $model, string $source, mixed $filter): self
    {
        return new self(sprintf(
            'The filter that %s gives to a controlled Typesense search of the [%s] model is [%s], not a string, so the package cannot AND it with the access filter. Pass a Typesense filter expression as a string, or leave filter_by out.',
            $source,
            $model::class,
            get_debug_type($filter),
        ));
    }

    public static function notSelfContained(Model $model, string $source, string $filter): self
    {
        return new self(sprintf(
            'The filter [%s] that %s gives to a controlled Typesense search of the [%s] model does not end on its own: Typesense would read it with a backtick-quoted value, a geo filter, a reference or an object filter still open at its end. The package ANDs the access filter after it, so it must be an expression that Typesense reads to its end: close every backtick, geo filter, reference and object filter.',
            $filter,
            $source,
            $model::class,
        ));
    }
}
