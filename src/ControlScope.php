<?php

namespace Lomkit\Access;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Client\Request;
use Lomkit\Access\Controls\Control;

class ControlScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['WithoutControl', 'WithControl'];

    /**
     * Apply the access control features to the query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (config('access-control.queries.enabled_by_default', true)) {
            $builder->withControl();
        }
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
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
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithControl(Builder $builder)
    {
        $builder->macro('withControl', function (Builder $builder) {
            /** @var Control $control */
            $control = $builder->getModel()->newControl();

            return $control->runQuery($builder);
        });
    }

    /**
     * Add the without-control extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithoutControl(Builder $builder)
    {
        $builder->macro('withoutControl', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}