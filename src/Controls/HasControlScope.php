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
     * Conditionally applies access control to the query builder.
     *
     * When the configuration 'access-control.queries.enabled_by_default' is enabled, this method invokes
     * the "controlled" macro on the provided query builder to enforce access control restrictions.
     * The $model parameter is included to satisfy interface requirements but is not used in this implementation.
     *
     * @param Builder $builder The query builder instance to be extended.
     * @param Model $model The model instance associated with the query (unused).
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
     * Registers a "controlled" macro on the Eloquent query builder.
     *
     * This macro extends the builder by creating a new Control instance from the model
     * and applying its queried method with the builder and the currently authenticated user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder The Eloquent query builder instance.
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
     * This macro enables bypassing the access control global scope by removing the current scope from the query builder,
     * allowing retrieval of unfiltered records.
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
