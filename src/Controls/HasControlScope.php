<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Exceptions\MissingControlException;

/** @implements Scope<Model> */
class HasControlScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected array $extensions = ['controlled', 'uncontrolled'];

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
     * @return Model|null The authenticated user, or null when there is none.
     */
    public static function resolveUser(): ?Model
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
     * @param  Builder<covariant Model>  $builder  The Eloquent query builder instance.
     * @param  Model  $model  The related model instance (unused in this method).
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (config('access-control.queries.enabled_by_default', false)) {
            /** @var Builder<Model> $builder */
            $this->controlled($builder);
        }
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Registers the "controlled" macro on the query builder.
     *
     * @param  Builder<Model>  $builder  The query builder instance to extend.
     */
    protected function addControlled(Builder $builder): void
    {
        $scope = $this;

        $builder->macro('controlled', function (Builder $builder) use ($scope) {
            return $scope->controlled($builder);
        });
    }

    /**
     * Registers the "uncontrolled" macro on the query builder.
     *
     * @param  Builder<Model>  $builder  The query builder instance to extend.
     */
    protected function addUncontrolled(Builder $builder): void
    {
        $scope = $this;

        $builder->macro('uncontrolled', function (Builder $builder) use ($scope) {
            return $builder->withoutGlobalScope($scope);
        });
    }

    /**
     * Applies the control of the builder's model for the authenticated user.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder  The query builder to control.
     * @return Builder<TModel> The controlled query builder.
     */
    protected function controlled(Builder $builder): Builder
    {
        if (static::retrievingUser()) {
            return $builder;
        }

        $model = $builder->getModel();

        /** @var Control $control */
        $control = method_exists($model, 'newControl') ? $model->newControl() : throw MissingControlException::forModel($model::class);

        return $control->queried($builder, static::resolveUser());
    }
}
