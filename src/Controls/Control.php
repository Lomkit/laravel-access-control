<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;
use Throwable;

class Control
{
    /**
     * The model the control refers to.
     *
     * @var class-string<Model>
     */
    protected string $model;

    /**
     * Does the given model match with the current one.
     *
     * @param class-string<Model> $model
     *
     * @return bool
     */
    public function isModel(string $model): bool
    {
        return $model === $this->model;
    }

    /**
     * Return the control current model.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

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
        // If the method is viewAny, we check instead if he has any 'view' right on different perimeters
        $appliesMethod = config(sprintf('access-control.methods.%s', $method)) ?? $method;

        foreach ($this->perimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, $appliesMethod)) {
                // If the model doesn't exist, it means the method is not related to a model
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

        if (config('access-control.queries.isolate_parent_query')) {
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
            if ($perimeter->applyAllowedCallback($user, config('access-control.methods.view'))) {
                if (config('access-control.queries.isolate_perimeter_queries')) {
                    $query = $query->orWhere(function (Builder $query) use ($user, $perimeter) {
                        $perimeter->applyQueryCallback($query, $user);
                    });
                } else {
                    $perimeter->applyQueryCallback($query, $user);
                }

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
            if ($perimeter->applyAllowedCallback($user, config('access-control.methods.view'))) {
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
     * Creates a new instance of the control.
     *
     * @return static A newly created control instance.
     */
    public static function new(): self
    {
        return new static();
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
