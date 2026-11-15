<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\JoinInPerimeterException;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Exceptions\UnlistableAbilityException;

/** @phpstan-require-extends Control */
trait ControlsQueries
{
    /**
     * @internal
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     *
     * @throws QueryCallbackReturnedAnotherBuilderException If a query callback returns another builder.
     * @throws EmptyQueryCallbackException If a query callback adds no constraint.
     * @throws JoinInPerimeterException If a query callback adds a join.
     * @throws UnlistableAbilityException If the ability is create.
     */
    final public function restrict(Builder $query, ?Model $reader, Ability $ability): void
    {
        if ($ability->is(Ability::create())) {
            throw UnlistableAbilityException::for($this, $ability);
        }

        if ($reader === null) {
            $query->whereRaw('0=1');

            return;
        }

        $this->restrictFor($query, AccessContext::of($reader, $ability));
    }

    /**
     * @internal
     *
     * @param  Builder<covariant Model>  $query
     *
     * @throws QueryCallbackReturnedAnotherBuilderException If a query callback returns another builder.
     * @throws EmptyQueryCallbackException If a query callback adds no constraint.
     * @throws JoinInPerimeterException If a query callback adds a join.
     */
    final public function restrictFor(Builder $query, AccessContext $context): void
    {
        $query->where(function (Builder $controlled) use ($context): void {
            $this->constrainControlled($controlled, $context);
        });
    }

    /** @param  Builder<covariant Model>  $query */
    private function constrainControlled(Builder $query, AccessContext $context): void
    {
        $perimeters = $this->declaration()->reachedBy($context);

        if ($perimeters->isEmpty()) {
            $query->whereRaw('0=1');

            return;
        }

        $this->declaration()->boundary()->applyScope($query, $context);

        if ($perimeters->reachesEveryRow()) {
            return;
        }

        (new PerimeterConstraints($this))->apply($query, $perimeters->all(), $context);
    }
}
