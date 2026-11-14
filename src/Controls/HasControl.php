<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingControlException;

trait HasControl
{
    public static function bootHasControl(): void
    {
        static::addGlobalScope(new HasControlScope);
    }

    /**
     * @throws MissingControlException When no control is registered for the model.
     */
    public function newControl(): Control
    {
        return Access::controlForModel(static::class) ?? throw MissingControlException::forModel(static::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function controlled(Builder $query): void
    {
        $reader = Auth::user();

        $this->controlledFor($query, $reader instanceof Model ? $reader : null);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function controlledFor(Builder $query, ?Model $reader): void
    {
        $control = $this->newControl();

        $query->withoutGlobalScope(HasControlScope::class);

        $control->queried($query, $reader);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function uncontrolled(Builder $query): void
    {
        $query->withoutGlobalScope(HasControlScope::class);
    }
}
