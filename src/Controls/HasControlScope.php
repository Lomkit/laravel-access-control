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
     * Applies the default access control to the Eloquent query builder.
     *
     * Checks the 'access-control.queries.enabled_by_default' configuration and, if enabled,
     * invokes the 'controlled' macro on the query builder to restrict results based on
     * the authenticated user's permissions.
     *
     * Note: The $model parameter is included for interface compatibility and is not used by this method.
     *
     * @param Builder $builder The Eloquent query builder instance.
     * @param Model $model The model instance (unused).
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
     * Adds the "controlled" macro to the Eloquent query builder.
     *
     * The macro extends the builder to apply access control conditions by retrieving a new control instance from the model and invoking its queried method with the current authenticated user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The query builder to extend.
     */
    protected function addControlled(Builder $builder): void
    {
        $builder->macro('controlled', function (Builder $builder) {
            /** @var Control $control */
            $control = $builder->getModel()->newControl();

            return $control->queried($builder, Auth::user());
        });
    }

    /**
     * Registers the "uncontrolled" macro on the query builder.
     *
     * The macro, when invoked, removes the current global control scope from the builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The Eloquent query builder instance.
     */
    protected function addUncontrolled(Builder $builder)
    {
        $builder->macro('uncontrolled', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
