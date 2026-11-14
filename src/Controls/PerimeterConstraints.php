<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\JoinInPerimeterException;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Perimeters\Perimeter;

/** @internal */
final readonly class PerimeterConstraints
{
    public function __construct(private Control $control) {}

    /**
     * @param  Builder<covariant Model>  $query
     * @param  array<Perimeter>  $perimeters
     *
     * @throws QueryCallbackReturnedAnotherBuilderException If a query callback returns another builder.
     * @throws EmptyQueryCallbackException If a query callback adds no constraint.
     * @throws JoinInPerimeterException If a query callback adds a join.
     */
    public function apply(Builder $query, array $perimeters, AccessContext $context): void
    {
        $query->where(function (Builder $rows) use ($perimeters, $context): void {
            foreach ($perimeters as $perimeter) {
                $rows->orWhere(function (Builder $perimeterRows) use ($perimeter, $context): void {
                    $this->constrain($perimeterRows, $perimeter, $context);
                });
            }
        });
    }

    /** @param  Builder<covariant Model>  $query */
    private function constrain(Builder $query, Perimeter $perimeter, AccessContext $context): void
    {
        $constraints = count($query->getQuery()->wheres);
        $joins = count($query->getQuery()->joins ?? []);

        $perimeter->applyQueryCallback($query, $context);

        if (count($query->getQuery()->wheres) === $constraints) {
            throw EmptyQueryCallbackException::for($this->control, $perimeter);
        }

        if (count($query->getQuery()->joins ?? []) !== $joins) {
            throw JoinInPerimeterException::forPerimeter($this->control, $perimeter);
        }
    }
}
