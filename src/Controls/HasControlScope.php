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
     * Apply the access control features to the query.
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
     * Add the with-control extension to the builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addControlled(Builder $builder):void
    {
        $builder->macro('controlled', function (Builder $builder) {
            /** @var Control $control */
            $control = $builder->getModel()->newControl();

            return $control->queried($builder, Auth::user());
        });
    }

    /**
     * Add the without-control extension to the builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
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
