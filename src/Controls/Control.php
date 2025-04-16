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
    // @TODO: change readme image
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
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<\Lomkit\Access\Perimeters\Perimeter> An array of Perimeter objects.
     */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * Determines if the control applies based on the user's permissions and model state.
     *
     * @param Model  $user   The user whose permissions are evaluated.
     * @param string $method The action or method to verify.
     * @param Model  $model  The target model; if it does not exist, the control applies by default.
     *
     * @return bool True if the control applies to the user and model; otherwise, false.
     */
    public function applies(Model $user, string $method, Model $model): bool
    {
        foreach ($this->perimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, $method)) {
                // If the model doesn't exists, it means the method is not related to a model
                // so we don't need to activate the should result since we can't compare an existing model
                if (!$model->exists) {
                    return true;
                }

                $should = $perimeter->applyShouldCallback($user, $model);

                if (!$perimeter->overlays() || $should) {
                    return $should;
                }
            }
        }

        return false;
    }

    /**
     * Applies access control restrictions to an Eloquent query builder for the specified user.
     *
     * @param Builder $query The Eloquent query builder to modify.
     * @param Model   $user  The user for whom access control is enforced.
     *
     * @return Builder The query builder with access control restrictions applied.
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
     * Applies access control restrictions to a Laravel Scout query builder for the specified user.
     *
     * @param \Laravel\Scout\Builder $query The Scout query builder to modify.
     * @param Model                  $user  The user for whom access control is enforced.
     *
     * @return \Laravel\Scout\Builder The query builder with access controls applied.
     */
    public function scoutQueried(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        return $this->applyScoutQueryControl($query, $user);
    }

    /**
     * Modifies an Eloquent query builder to enforce access control rules for the specified user.
     *
     * @param Builder $query The Eloquent query builder to modify.
     * @param Model   $user  The user for whom access control is evaluated.
     *
     * @return Builder The modified query builder reflecting access control restrictions.
     */
    protected function applyQueryControl(Builder $query, Model $user): Builder
    {
        $noResultCallback = function (Builder $query) {
            return $this->noResultQuery($query);
        };

        foreach ($this->perimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, 'view')) {
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
     * Applies access control modifications to a Laravel Scout query builder based on defined perimeters.
     *
     * @param \Laravel\Scout\Builder $query The Scout query builder to modify.
     * @param Model                  $user  The user for whom access control is being enforced.
     *
     * @return \Laravel\Scout\Builder The modified Scout query builder reflecting access control restrictions.
     */
    protected function applyScoutQueryControl(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        $noResultCallback = function (\Laravel\Scout\Builder $query) {
            return $this->noResultScoutQuery($query);
        };

        foreach ($this->perimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, 'view')) {
                $query = $perimeter->applyScoutQueryCallback($query, $user);

                $noResultCallback = function ($query) {return $query; };

                if (!$perimeter->overlays()) {
                    return $query;
                }
            }
        }

        return $noResultCallback($query);
    }

    /**
     * Alters the Eloquent query builder to ensure no records are returned.
     *
     * @param Builder $query The Eloquent query builder to modify.
     *
     * @return Builder The query builder configured to yield no results.
     */
    protected function noResultQuery(Builder $query): Builder
    {
        return $query->whereRaw('0=1');
    }

    /**
     * Modifies the Scout query builder to ensure no records are returned.
     *
     * @param \Laravel\Scout\Builder $query The Scout query builder to modify.
     *
     * @return \Laravel\Scout\Builder The modified query builder that yields no results.
     */
    protected function noResultScoutQuery(\Laravel\Scout\Builder $query): \Laravel\Scout\Builder
    {
        return $query->where('__NOT_A_VALID_FIELD__', 0);
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
     * Creates a new instance of the control.
     *
     * @return static A newly created control instance.
     */
    public static function new(): self
    {
        return new static();
    }

    /**
     * Resolve the control name for a given model.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TClass> $modelName The fully qualified model class name.
     *
     * @return class-string<\Lomkit\Access\Controls\Control<TClass>> The fully qualified control class name corresponding to the model.
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
     * Retrieves the application's namespace.
     *
     * @return string The resolved or default application namespace.
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
