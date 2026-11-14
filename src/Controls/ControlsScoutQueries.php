<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\NoBoundary;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;

/** @phpstan-require-extends Control */
trait ControlsScoutQueries
{
    /**
     * @internal
     *
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
     * @throws UnsearchableBoundaryException If the control declares a boundary.
     */
    protected function applyScoutQueryControl(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        if (! $this->declaration()->boundary() instanceof NoBoundary) {
            throw UnsearchableBoundaryException::for($this);
        }

        $context = AccessContext::of($user, Ability::view());
        $perimeters = $this->declaration()->reachedBy($context);

        if ($perimeters->isEmpty()) {
            return $this->noResultScoutQuery($query);
        }

        if ($perimeters->reachesEveryRow()) {
            return $query;
        }

        foreach ($perimeters->all() as $perimeter) {
            $perimeter->applyScoutQueryCallback($query, $context);
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
