<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
final class HasControlScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    #[\Override]
    public function apply(Builder $builder, Model $model): void {}
}
