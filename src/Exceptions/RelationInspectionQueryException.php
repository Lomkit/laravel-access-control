<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\QueryException;
use LogicException;

final class RelationInspectionQueryException extends LogicException
{
    public static function for(string $model, string $relation, string $connection, ?QueryException $query = null): self
    {
        return new self(sprintf(
            'The [%s] relation of the [%s] model runs a query on the [%s] connection when the method is called%s. The package reads the shape of a relation (its related model and its keys) without a database connection, so that a model on a connection configured later is checked anywhere: build the relation from its keys alone, and move the query out of the [%s::%s()] method.',
            $relation,
            $model,
            $connection,
            $query === null ? '' : sprintf(' ([%s])', $query->getSql()),
            $model,
            $relation,
        ), previous: $query);
    }
}
