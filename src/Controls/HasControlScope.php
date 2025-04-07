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
     * Applies the default access control constraint to the query builder.
     *
     * If the configuration for default access control queries is enabled, this method
     * modifies the given query builder by invoking the "controlled" macro to enforce
     * access control constraints.
     *
     * @param Builder $builder The query builder instance to be modified.
     * @param Model   $model   The model instance associated with the query (provided for interface compatibility).
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
     * The macro instantiates a new Control instance via the model's newControl method and applies
     * access control logic by invoking the Control's queried method with the builder and the
     * currently authenticated user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
     *
     * @return void
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
     * This macro removes the current global control scope from the builder,
     * allowing queries to bypass the access control constraints.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The query builder instance.
     */
    protected function addUncontrolled(Builder $builder)
    {
        $builder->macro('uncontrolled', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
