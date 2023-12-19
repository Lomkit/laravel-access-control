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
    protected $extensions = [];

    /**
     * Apply the access control features to the query.
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var Control $control */
        $control = $model->newControl();

        $control->runQuery($builder);
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
}