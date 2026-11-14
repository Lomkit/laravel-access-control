<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Perimeters\Perimeter;

/** @phpstan-require-extends Control */
trait ControlsQueries
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function queried(Builder $query, ?Model $user): Builder
    {
        if (is_null($user)) {
            return $this->noResultQuery($query);
        }

        return $query->where(function (Builder $query) use ($user) {
            $this->applyQueryControl($query, $user);
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
        $perimeters = $this->applicablePerimeters($user, $this->viewMethod());

        if ($perimeters === []) {
            return $this->noResultQuery($query);
        }

        if ($this->reachesEveryRow($perimeters)) {
            return $query;
        }

        foreach ($perimeters as $perimeter) {
            $query->orWhere(function (Builder $query) use ($user, $perimeter) {
                $this->constrainByPerimeter($query, $perimeter, $user);
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
    private function constrainByPerimeter(Builder $query, Perimeter $perimeter, Model $user): void
    {
        $constraints = count($query->getQuery()->wheres);

        if ($perimeter->applyQueryCallback($query, $user) !== $query) {
            throw QueryCallbackReturnedAnotherBuilderException::for($this, $perimeter, 'query');
        }

        if (count($query->getQuery()->wheres) === $constraints) {
            throw EmptyQueryCallbackException::for($this, $perimeter);
        }
    }
}
