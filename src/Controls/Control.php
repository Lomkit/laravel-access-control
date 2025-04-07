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
     * Retrieves the perimeter instances associated with the control.
     *
     * This base implementation returns an empty array and should be overridden in subclasses to provide actual perimeter logic.
     *
     * @return Perimeter[] An array of perimeter instances.
     */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * Determines if a given action is permitted for a user on a model.
     *
     * Iterates through all defined perimeters and evaluates the user's permissions:
     * - If a perimeter's allowed callback returns true and the model does not exist, access is granted.
     * - If the model exists, the method evaluates the should callback and returns its result if the
     *   perimeter does not overlay or the callback returns true.
     * Returns false if no perimeter permits the action.
     *
     * @param Model  $user   The user whose permissions are checked.
     * @param string $method The action name under consideration.
     * @param Model  $model  The model instance that the action targets.
     *
     * @return bool True if access is allowed, false otherwise.
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
     * Modifies the query builder to apply access control filters for the provided user.
     *
     * Depending on the configuration, if isolated queries are enabled, the access control modifications
     * are wrapped within a query closure; otherwise, they are applied directly. This method leverages
     * the applyQueryControl method to enforce query-level access restrictions based on user permissions.
     *
     * @param Builder $query The query builder instance to modify.
     * @param Model   $user  The user model whose permissions determine the applied query filters.
     *
     * @return Builder The modified query builder instance.
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
     * Applies query modifications based on user permissions and defined perimeters.
     *
     * Iterates over each perimeter, applying its query callback if the user is allowed.
     * If a perimeter does not overlay its modifications, the updated query is returned immediately.
     * Otherwise, if no perimeter fully authorizes the query, the final query is adjusted to yield no results.
     *
     * @param Builder $query The query builder instance to modify.
     * @param Model   $user  The user model for permission checks.
     *
     * @return Builder The modified query builder instance.
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
     * Returns a query that yields no results.
     *
     * This method modifies the provided query builder by applying a condition that always evaluates to false,
     * ensuring that the query returns no matching records.
     *
     * @param Builder $query The query builder instance to modify.
     *
     * @return Builder The modified query builder configured to return no results.
     */
    protected function noResultQuery(Builder $query): Builder
    {
        return $query->whereRaw('0=1');
    }

    /**
     * Specify the callback that should be invoked to guess control names.
     *
     * @param callable(class-string<\Illuminate\Database\Eloquent\Model>): class-string<\Lomkit\Access\Controls\Control> $callback
     *
     * @return void
     */
    public static function guessControlNamesUsing(callable $callback): void
    {
        static::$controlNameResolver = $callback;
    }

    /**
     * Get a new control instance for the given model name.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TClass> $modelName
     *
     * @return \Lomkit\Access\Controls\Control<TClass>
     */
    public static function controlForModel(string $modelName): self
    {
        $control = static::resolveControlName($modelName);

        return $control::new();
    }

    /**
     * Creates and returns a new instance of the control class.
     *
     * This method uses late static binding to instantiate a control object corresponding
     * to the called static class.
     *
     * @return static A newly created control instance.
     */
    public static function new(): self
    {
        return new static();
    }

    /**
     * Resolves the fully-qualified control class name for the specified model.
     *
     * If a custom resolver is provided via `guessControlNamesUsing()`, that callback is used;
     * otherwise, the control name is derived by stripping the application namespace from the model
     * name and appending the default control namespace and a "Control" suffix.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TClass> $modelName The fully-qualified model class name.
     *
     * @return class-string<\Lomkit\Access\Controls\Control<TClass>> The resolved control class name.
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
     * Retrieves the application's base namespace.
     *
     * Attempts to obtain the namespace from the application container. If an error occurs during retrieval,
     * it defaults to returning 'App\\'.
     *
     * @return string The determined application namespace or 'App\\' if retrieval fails.
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
