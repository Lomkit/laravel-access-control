<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @internal */
final class ConstraintGroup
{
    /** @param  Builder<covariant Model>  $query */
    public static function wrap(Builder $query): void
    {
        $base = $query->getQuery();

        if ($base->wheres === []) {
            return;
        }

        $group = $base->forNestedWhere();
        $group->wheres = $base->wheres;
        $group->bindings['where'] = $base->bindings['where'];

        $base->wheres = [];
        $base->bindings['where'] = [];
        $base->addNestedWhereQuery($group);
    }
}
