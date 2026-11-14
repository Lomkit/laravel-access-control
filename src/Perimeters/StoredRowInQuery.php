<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\StoredRow;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\JoinInPerimeterException;

/** @internal */
final class StoredRowInQuery
{
    /**
     * @throws EmptyQueryCallbackException If the query callback adds no constraint.
     * @throws JoinInPerimeterException If the query callback adds a join.
     */
    public static function contains(Perimeter $perimeter, Model $row, AccessContext $context): bool
    {
        if (! $row->exists) {
            return false;
        }

        return $row
            ->newQueryWithoutScopes()
            ->whereKey(StoredRow::of($row)->getKey())
            ->where(function (Builder $rows) use ($perimeter, $row, $context): void {
                $perimeter->applyQueryCallback($rows, $context);

                if ($rows->getQuery()->wheres === []) {
                    throw EmptyQueryCallbackException::forModel($perimeter, $row::class);
                }

                if (($rows->getQuery()->joins ?? []) !== []) {
                    throw JoinInPerimeterException::forPerimeterOf($perimeter, $row::class);
                }
            })
            ->exists();
    }
}
