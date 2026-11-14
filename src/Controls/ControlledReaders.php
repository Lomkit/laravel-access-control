<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/** @internal */
final class ControlledReaders
{
    /** @var WeakMap<object, ControlledReader> */
    private WeakMap $readers;

    public function __construct()
    {
        $this->readers = new WeakMap;
    }

    /** @param  Builder<covariant Model>  $query */
    public function remember(Builder $query, ?Model $reader): void
    {
        $controlled = new ControlledReader($reader);

        $this->readers[$query] = $controlled;

        $query->onClone(function (Builder $clone) use ($controlled): void {
            $this->readers[$clone] = $controlled;
        });
    }

    /** @param  Builder<covariant Model>  $query */
    public function of(Builder $query): ?ControlledReader
    {
        return $this->readers[$query] ?? null;
    }
}
