<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Access\Perimeters\Perimeter;
use Throwable;

class Control
{
    // @TODO: scout queried
    /**
     * The control name resolver.
     *
     * @var callable
     */
    protected static $controlNameResolver;

    /**
     * The default namespace where control reside.
     *
     * @var string
     */
    public static $namespace = 'App\\Access\\Controls\\';

    /**
     * Returns the perimeters applicable to the control.
     *
     * This method returns an empty array by default and is intended to be overridden
     * by subclasses to provide specific Perimeter instances that define access constraints.
     *
     * @return array<Perimeter> An array of perimeter objects.
     */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * Determines if the control applies for the user's action on a given model.
     *
     * Iterates over each perimeter and checks if the allowed callback passes for the user.
     * If the target model does not exist, the function returns true immediately.
     * Otherwise, it evaluates the "should" callback with the user, method, and model, returning its result
     * if the perimeter does not overlay or if the "should" callback yields a truthy value.
     *
     * @param Model  $user   The user attempting the action.
     * @param string $method The action method being checked.
     * @param Model  $model  The target model for the action.
     *
     * @return bool True if the control applies, false otherwise.
     */
    public function applies(Model $user, string $method, Model $model): bool
    {
        foreach ($this->perimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user)) {
                // If the model doesn't exists, it means the method is not related to a model
                // so we don't need to activate the should result since we can't compare an existing model
                if (!$model->exists) {
                    return true;
                }

                $should = $perimeter->applyShouldCallback($user, $method, $model);

                if (!$perimeter->overlays() || $should) {
                    return $should;
                }
            }
        }

        return false;
    }

    /**
     * Applies access control filters to the query builder for a given user.
     *
     * This method modifies the provided query by applying additional query controls based on the user's permissions.
     * If isolated queries are enabled via configuration ('access-control.queries.isolated'), the access control
     * constraints are wrapped within a where clause to limit their scope.
     *
     * @param Builder $query The query builder instance to modify.
     * @param Model   $user  The user for whom the query control is applied.
     *
     * @return Builder The modified query builder with the access control filters applied.
     */
    public function queried(Builder $query, Model $user): Builder
    {
        $callback = function (Builder $query, Model $user) {
            return $this->applyQueryControl($query, $user);
        };

        if (config('access-control.queries.isolated')) {
            return $query->where(function (Builder $query) use ($user, $callback) {
                $callback($query, $user);
            });
        }

        return $callback($query, $user);
    }

    /**
     * Applies query modifications based on user-specific access controls.
     *
     * Iterates through the configured perimeters and, for each perimeter that permits access for the given user,
     * applies a callback to modify the query builder. If a permitted perimeter does not overlay additional controls,
     * the modified query is returned immediately. Otherwise, a fallback is applied to yield a query with no results.
     *
     * @param Builder $query The query builder instance to modify.
     * @param Model   $user  The user model used to evaluate access controls.
     *
     * @return Builder The query builder after applying access control filters.
     */
    protected function applyQueryControl(Builder $query, Model $user): Builder
    {
        $noResultCallback = function (Builder $query) {
            return $this->noResultQuery($query);
        };

        foreach ($this->perimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user)) {
                $query = $perimeter->applyQueryCallback($query, $user);

                $noResultCallback = function ($query) {return $query; };

                if (!$perimeter->overlays()) {
                    return $query;
                }
            }
        }

        return $noResultCallback($query);
    }

    /**
     * Modifies the query to ensure it returns no results.
     *
     * This method appends a raw where clause ("0=1") to the provided query builder,
     * effectively filtering out all records.
     *
     * @param Builder $query The query builder instance to modify.
     *
     * @return Builder The modified query builder that yields an empty result set.
     */
    protected function noResultQuery(Builder $query): Builder
    {
        return $query->whereRaw('0=1');
    }

    /**
     * Sets the callback used to resolve control names for models.
     *
     * The callback should accept a fully-qualified model class name and return the corresponding
     * fully-qualified control class name.
     *
     * @param callable(class-string<\Illuminate\Database\Eloquent\Model>):class-string<\Lomkit\Access\Controls\Control> $callback Callback to resolve control names.
     */
    public static function guessControlNamesUsing(callable $callback): void
    {
        static::$controlNameResolver = $callback;
    }

    /**
     * Creates and returns a new control instance associated with the specified model.
     *
     * This method resolves the appropriate control name for the given model class
     * and instantiates a new control using the resolved control's static new() method.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TClass> $modelName The fully-qualified class name of the model.
     *
     * @return \Lomkit\Access\Controls\Control<TClass> A new control instance for the specified model.
     */
    public static function controlForModel(string $modelName): self
    {
        $control = static::resolveControlName($modelName);

        return $control::new();
    }

    /**
     * Creates and returns a new instance of the current control.
     *
     * This factory method uses late static binding, allowing subclass overrides to correctly instantiate their own type.
     *
     * @return static A new instance of the control.
     */
    public static function new(): self
    {
        return new static();
    }

    /**
     * Get the control name for the given model name.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TClass> $modelName
     *
     * @return class-string<\Lomkit\Access\Controls\Control<TClass>>
     */
    public static function resolveControlName(string $modelName): string
    {
        $resolver = static::$controlNameResolver ?? function (string $modelName) {
            $appNamespace = static::appNamespace();

            $modelName = Str::startsWith($modelName, $appNamespace.'Models\\')
                ? Str::after($modelName, $appNamespace.'Models\\')
                : Str::after($modelName, $appNamespace);

            return static::$namespace.$modelName.'Control';
        };

        return $resolver($modelName);
    }

    /**
     * Retrieves the application namespace from the service container.
     *
     * If the application instance cannot be resolved, returns the default namespace 'App\'.
     *
     * @return string The resolved application namespace, or 'App\' if resolution fails.
     */
    protected static function appNamespace(): string
    {
        try {
            return Container::getInstance()
                ->make(Application::class)
                ->getNamespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }
}
