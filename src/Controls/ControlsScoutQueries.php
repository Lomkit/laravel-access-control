<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;

/** @phpstan-require-extends Control */
trait ControlsScoutQueries
{
    /**
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query
     * @return \Laravel\Scout\Builder<TModel>
     */
    public function scoutQueried(\Laravel\Scout\Builder $query, ?Model $user): \Laravel\Scout\Builder
    {
        if (is_null($user)) {
            return $this->noResultScoutQuery($query);
        }

        return $this->applyScoutQueryControl($query, $user);
    }

    /**
     * Scout only combines constraints with "and": when several perimeters apply, their constraints narrow each other.
     *
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query
     * @return \Laravel\Scout\Builder<TModel>
     *
     * @throws QueryCallbackReturnedAnotherBuilderException If a Scout query callback returns another builder.
     */
    protected function applyScoutQueryControl(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        $perimeters = $this->applicablePerimeters($user, $this->viewMethod());

        if ($perimeters === []) {
            return $this->noResultScoutQuery($query);
        }

        if ($this->reachesEveryRow($perimeters)) {
            return $query;
        }

        foreach ($perimeters as $perimeter) {
            if ($perimeter->applyScoutQueryCallback($query, $user) !== $query) {
                throw QueryCallbackReturnedAnotherBuilderException::for($this, $perimeter, 'scoutQuery');
            }
        }

        return $query;
    }

    /**
     * Scout uses what the search callback returns as the raw results, so the engine is never queried; the database and
     * collection engines hand the callback their Eloquent query instead, which is constrained to match nothing.
     *
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query
     * @return \Laravel\Scout\Builder<TModel>
     */
    protected function noResultScoutQuery(\Laravel\Scout\Builder $query): \Laravel\Scout\Builder
    {
        $query->callback = static function (mixed $search): mixed {
            if ($search instanceof Builder) {
                return $search->whereRaw('0=1');
            }

            return [
                'hits' => [],
                'nbHits' => 0,
                'totalHits' => 0,
                'estimatedTotalHits' => 0,
                'found' => 0,
                'rows' => [],
                'total' => 0,
            ];
        };

        return $query;
    }
}
