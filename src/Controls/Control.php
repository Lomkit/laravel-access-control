<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;
use Throwable;

/** @phpstan-consistent-constructor */
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
     * @param  class-string<Model>  $model
     */
    public function isModel(string $model): bool
    {
        return $model === $this->model;
    }

    /**
     * Return the control current model.
     *
     * @return class-string<Model>
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Retrieve the list of perimeter definitions for the current control.
     *
     * @return array<Perimeter> An array of Perimeter objects.
     */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * The perimeters of this control, built once per instance.
     *
     * @var array<Perimeter>|null
     */
    private ?array $resolvedPerimeters = null;

    /**
     * Get the perimeters of this control, building them on first use.
     *
     * @return array<Perimeter>
     */
    public function getPerimeters(): array
    {
        return $this->resolvedPerimeters ??= $this->perimeters();
    }

    /**
     * Determines if the control applies based on the user's permissions and model state.
     *
     * @param  Model  $user  The user whose permissions are evaluated.
     * @param  string  $method  The action or method to verify.
     * @param  Model  $model  The target model; if it does not exist, the control applies by default.
     * @return bool True if the control applies to the user and model; otherwise, false.
     */
    public function applies(Model $user, string $method, Model $model): bool
    {
        // If the method is viewAny, we check instead if he has any 'view' right on different perimeters
        /** @var string $appliesMethod */
        $appliesMethod = config(sprintf('access-control.methods.%s', $method)) ?? $method;

        foreach ($this->getPerimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, $appliesMethod)) {
                // If the model doesn't exist, it means the method is not related to a model
                // so we don't need to activate the should result since we can't compare an existing model
                if (! $model->exists) {
                    return true;
                }

                $should = $perimeter->applyShouldCallback($user, $model);

                if (! $perimeter->overlays() || $should) {
                    return $should;
                }
            }
        }

        return false;
    }

    /**
     * Applies access control restrictions to an Eloquent query builder for the specified user.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  The Eloquent query builder to modify.
     * @param  Model|null  $user  The user for whom access control is enforced; if null, the query yields no results.
     * @return Builder<TModel> The query builder with access control restrictions applied.
     */
    public function queried(Builder $query, ?Model $user): Builder
    {
        if (is_null($user)) {
            return $this->noResultQuery($query);
        }

        // The perimeters are grouped in one nested where so their "or" never leaks into the caller's constraints
        return $query->where(function (Builder $query) use ($user) {
            $this->applyQueryControl($query, $user);
        });
    }

    /**
     * Applies access control restrictions to a Laravel Scout query builder for the specified user.
     *
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query  The Scout query builder to modify.
     * @param  Model|null  $user  The user for whom access control is enforced; if null, the query yields no results.
     * @return \Laravel\Scout\Builder<TModel> The query builder with access controls applied.
     */
    public function scoutQueried(\Laravel\Scout\Builder $query, ?Model $user): \Laravel\Scout\Builder
    {
        if (is_null($user)) {
            return $this->noResultScoutQuery($query);
        }

        return $this->applyScoutQueryControl($query, $user);
    }

    /**
     * Modifies an Eloquent query builder to enforce access control rules for the specified user.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  The Eloquent query builder to modify.
     * @param  Model  $user  The user for whom access control is evaluated.
     * @return Builder<TModel> The modified query builder reflecting access control restrictions.
     */
    protected function applyQueryControl(Builder $query, Model $user): Builder
    {
        $noResultCallback = function (Builder $query) {
            return $this->noResultQuery($query);
        };

        /** @var string $viewMethod */
        $viewMethod = config('access-control.methods.view');

        foreach ($this->getPerimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, $viewMethod)) {
                // Each perimeter gets its own group so overlaid perimeters add up instead of narrowing each other
                $query->orWhere(function (Builder $query) use ($user, $perimeter) {
                    $perimeter->applyQueryCallback($query, $user);
                });

                $noResultCallback = function (Builder $query): Builder {
                    return $query;
                };

                if (! $perimeter->overlays()) {
                    return $query;
                }
            }
        }

        return $noResultCallback($query);
    }

    /**
     * Applies access control modifications to a Laravel Scout query builder based on defined perimeters.
     *
     * Scout only combines constraints with "and": when several perimeters apply (an overlay perimeter followed by another one),
     * their constraints narrow each other instead of adding up as they do in an Eloquent query.
     *
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query  The Scout query builder to modify.
     * @param  Model  $user  The user for whom access control is being enforced.
     * @return \Laravel\Scout\Builder<TModel> The modified Scout query builder reflecting access control restrictions.
     */
    protected function applyScoutQueryControl(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        /** @var string $viewMethod */
        $viewMethod = config('access-control.methods.view');

        $perimeters = [];

        foreach ($this->getPerimeters() as $perimeter) {
            if ($perimeter->applyAllowedCallback($user, $viewMethod)) {
                $perimeters[] = $perimeter;

                if (! $perimeter->overlays()) {
                    break;
                }
            }
        }

        if ($perimeters === []) {
            return $this->noResultScoutQuery($query);
        }

        foreach ($perimeters as $perimeter) {
            $query = $perimeter->applyScoutQueryCallback($query, $user);
        }

        return $query;
    }

    /**
     * Alters the Eloquent query builder to ensure no records are returned.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  The Eloquent query builder to modify.
     * @return Builder<TModel> The query builder configured to yield no results.
     */
    protected function noResultQuery(Builder $query): Builder
    {
        return $query->whereRaw('0=1');
    }

    /**
     * Modifies the Scout query builder to ensure no records are returned.
     *
     * Scout hands the search callback the engine client and uses what it returns as the raw results, so the engine is never
     * queried and nothing depends on a filter the engine may reject. The database and collection engines hand it their
     * Eloquent query instead, which is constrained to match nothing.
     *
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query  The Scout query builder to modify.
     * @return \Laravel\Scout\Builder<TModel> The modified query builder that yields no results.
     */
    protected function noResultScoutQuery(\Laravel\Scout\Builder $query): \Laravel\Scout\Builder
    {
        $query->callback = static function (mixed $search): mixed {
            if ($search instanceof Builder) {
                return $search->whereRaw('0=1');
            }

            // An empty page in the raw shape of each engine: Algolia, Meilisearch, Typesense and Turbopuffer
            return [
                'hits' => [],
                'nbHits' => 0,
                'totalHits' => 0,
                'estimatedTotalHits' => 0,
                'found' => 0,
                'rows' => [],
                'total' => 0,
            ];
        };

        return $query;
    }

    /**
     * Creates a new instance of the control.
     *
     * @return static A newly created control instance.
     */
    public static function new(): self
    {
        return new static;
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
