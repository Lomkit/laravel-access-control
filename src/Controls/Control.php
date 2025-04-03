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
     * Get the perimeters for the current control.
     *
     * @return array<Perimeter>
     */
    protected function perimeters(): array
    {
        return [];
    }

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
     * Get a new control instance for the given attributes.
     *
     * @return static
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
     * Get the application namespace for the application.
     *
     * @return string
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
