<?php

namespace Lomkit\Access\Controls;

use Illuminate\Contracts\Auth\Authenticatable;
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
     * Whether the authenticated user is being resolved.
     */
    protected static bool $resolvingUser = false;

    /**
     * Resolve the authenticated user the controlled queries are built for.
     *
     * The queries run by the guard to retrieve the user are not controlled: the user
     * model may itself be controlled, which would resolve the user again while the
     * guard has not stored it yet.
     *
     * @return Authenticatable|null The authenticated user, or null when there is none.
     */
    public static function resolveUser(): ?Authenticatable
    {
        $wasResolvingUser = static::$resolvingUser;

        static::$resolvingUser = true;

        try {
            return Auth::user();
        } finally {
            static::$resolvingUser = $wasResolvingUser;
        }
    }

    /**
     * Determine if the guard is retrieving the authenticated user.
     *
     * @return bool True while the user is being resolved and the guard has not stored it yet.
     */
    public static function retrievingUser(): bool
    {
        return static::$resolvingUser && ! Auth::hasUser();
    }

    /**
     * Applies access control to the query builder.
     *
     * @param  Builder  $builder  The Eloquent query builder instance.
     * @param  Model  $model  The related model instance (unused in this method).
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
     * @param  Builder  $builder  The query builder instance to extend.
     */
    protected function addControlled(Builder $builder): void
    {
        $builder->macro('controlled', function (Builder $builder) {
            if (HasControlScope::retrievingUser()) {
                return $builder;
            }

            /** @var Control $control */
            $control = $builder->getModel()->newControl();

            return $control->queried($builder, HasControlScope::resolveUser());
        });
    }

    /**
     * Registers the "uncontrolled" macro on the query builder.
     *
     * @param  Builder  $builder  The query builder instance to extend.
     * @return void
     */
    protected function addUncontrolled(Builder $builder)
    {
        $builder->macro('uncontrolled', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
