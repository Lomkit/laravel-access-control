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
     * Retrieves the perimeters associated with the current control.
     *
     * This method returns an empty array by default and is intended to be overridden
     * by subclasses to define specific perimeter configurations that govern access control.
     *
     * @return Perimeter[] An array of perimeter objects.
     */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * Determines if the specified access method is permitted for the user on the target model.
     *
     * This method iterates through each defined perimeter. For a perimeter where the user's allowed callback passes,
     * it immediately grants access if the model does not exist. Otherwise, it evaluates the perimeter's conditional
     * (should) callback and, if the perimeter does not overlay permissions or the check passes, returns its result.
     * If no perimeter grants access, the method returns false.
     *
     * @param Model  $user   The user whose access is being evaluated.
     * @param string $method The access method to check.
     * @param Model  $model  The model relevant to the access check.
     *
     * @return bool True if access is permitted; otherwise, false.
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
     * Modifies the query to apply access control restrictions based on the specified user.
     *
     * If isolated queries are enabled via configuration ('access-control.queries.isolated'),
     * the access control logic is encapsulated within a WHERE clause. Otherwise, the control
     * logic is applied directly to the query.
     *
     * @param Builder $query The query builder instance to modify.
     * @param Model   $user  The user for whom the access control restrictions are applied.
     *
     * @return Builder The modified query builder with applied control conditions.
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
     * Applies query modifications based on access control perimeters for the given user.
     *
     * Iterates through each defined perimeter and, if the perimeter's allowed callback returns true
     * for the user, applies its query modifications. If a perimeter does not overlay further controls,
     * the modified query is returned immediately. If no allowable perimeter is found, returns a query
     * that yields no results.
     *
     * @param Builder $query The query builder instance to modify.
     * @param Model   $user  The user model used to determine permitted query modifications.
     *
     * @return Builder The modified query builder reflecting applied access control conditions.
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
     * Returns a query builder that yields no results.
     *
     * Modifies the given query by appending a condition that always evaluates to false, ensuring that the query returns no records.
     *
     * @return Builder The modified query builder instance.
     */
    protected function noResultQuery(Builder $query): Builder
    {
        return $query->whereRaw('0=1');
    }

    /**
     * Sets the callback used to resolve control names from model names.
     *
     * The provided callback should accept a fully-qualified model class name and return the corresponding
     * fully-qualified control class name. This callback is used for dynamic control name resolution.
     *
     * @param callable(class-string<\Illuminate\Database\Eloquent\Model>): class-string<\Lomkit\Access\Controls\Control> $callback A callback to map model class names to control class names.
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
     * Creates and returns a new control instance.
     *
     * @return static A new instance of the control class.
     */
    public static function new(): self
    {
        return new static();
    }

    /**
     * Resolves and returns the fully qualified control class name for the given model name.
     *
     * If a custom control name resolver has been set using guessControlNamesUsing(), it is used to compute
     * the control name. Otherwise, a default naming convention is applied by stripping the application namespace
     * from the model name, prepending the control namespace, and appending "Control".
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TClass> $modelName The fully qualified model class name.
     *
     * @return class-string<\Lomkit\Access\Controls\Control<TClass>> The fully qualified control class name.
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
     * Retrieves the application namespace.
     *
     * This method returns the application's namespace as configured. If an error occurs during the retrieval process,
     * it falls back to returning the default namespace "App\\".
     *
     * @return string The application namespace or "App\\" if an error is encountered.
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
