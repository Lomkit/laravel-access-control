<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Perimeters\Perimeter;

/** @phpstan-require-extends Control */
trait ControlsQueries
{
    /**
     * @internal
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    final public function restrict(Builder $query, ?Model $reader): void
    {
        if ($reader === null) {
            $this->noResultQuery($query);

            return;
        }

        $query->where(function (Builder $query) use ($reader) {
            $this->applyQueryControl($query, $reader);
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     *
     * @throws QueryCallbackReturnedAnotherBuilderException If a query callback returns another builder.
     * @throws EmptyQueryCallbackException If a query callback adds no constraint.
     */
    protected function applyQueryControl(Builder $query, Model $user): Builder
    {
        $context = AccessContext::of($user, Ability::view());
        $perimeters = $this->perimetersApplicableTo($context);

        if ($perimeters === []) {
            return $this->noResultQuery($query);
        }

        if ($this->reachesEveryRow($perimeters)) {
            return $query;
        }

        foreach ($perimeters as $perimeter) {
            $query->orWhere(function (Builder $query) use ($context, $perimeter) {
                $this->constrainByPerimeter($query, $perimeter, $context);
            });
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function noResultQuery(Builder $query): Builder
    {
        return $query->whereRaw('0=1');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function constrainByPerimeter(Builder $query, Perimeter $perimeter, AccessContext $context): void
    {
        $constraints = count($query->getQuery()->wheres);

        $perimeter->applyQueryCallback($query, $context);

        if (count($query->getQuery()->wheres) === $constraints) {
            throw EmptyQueryCallbackException::for($this, $perimeter);
        }
    }
}
