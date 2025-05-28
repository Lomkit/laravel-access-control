<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class HasControlScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['controlled', 'uncontrolled'];

    /**
     * Applies access control to the query builder.
     *
     * @param Builder $builder The Eloquent query builder instance.
     * @param Model   $model   The related model instance (unused in this method).
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (config('access-control.queries.enabled_by_default', false)) {
            $builder->controlled();
        }
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Registers the "controlled" macro on the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance to extend.
     *
     * @return void
     */
    protected function addControlled(Builder $builder): void
    {
        $builder->macro('controlled', function (Builder $builder) {
            /** @var Control $control */
            $control = $builder->getModel()::control();

            return $control->queried($builder, Auth::user());
        });
    }

    /**
     * Registers the "uncontrolled" macro on the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance to extend.
     *
     * @return void
     */
    protected function addUncontrolled(Builder $builder)
    {
        $builder->macro('uncontrolled', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
