<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Perimeter
{
    protected Closure $scoutQueryCallback;

    protected Closure $queryCallback;
    protected Closure $shouldCallback;
    protected Closure $allowedCallback;

    /**
     * Initializes the Perimeter with default callbacks for access control and query customization.
     *
     * By default, all callbacks either return their input unchanged or allow access, enabling further customization through setter methods.
     */
    public function __construct()
    {
        // Default implementations that can be overridden
        $this->scoutQueryCallback = function (\Laravel\Scout\Builder $query, Model $user) { return $query; };
        $this->queryCallback = function (Builder $query, Model $user) { return $query; };
        $this->shouldCallback = function (Model $user, string $method, Model $model) { return true; };
        $this->allowedCallback = function (Model $user) { return true; };
    }

    /**
     * Determines if the access control condition should be applied for the given user, method, and model.
     *
     * @param Model  $user   The user being evaluated.
     * @param string $method The access control action or method.
     * @param Model  $model  The related model instance.
     *
     * @return bool True if the condition applies; false otherwise.
     */
    public function applyShouldCallback(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

    /**
     * Applies the configured Scout query callback to modify a Laravel Scout search query for a given user.
     *
     * @param \Laravel\Scout\Builder $query The Scout query builder to modify.
     * @param Model                  $user  The user model for whom the query is being modified.
     *
     * @return \Laravel\Scout\Builder The modified Scout query builder.
     */
    public function applyScoutQueryCallback(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        return ($this->scoutQueryCallback)($query, $user);
    }

    /**
     * Applies the registered query callback to modify the query builder based on the user's context.
     *
     * @param Builder $query The query builder instance to be customized.
     * @param Model   $user  The user model providing context for the query modification.
     *
     * @return Builder The modified query builder.
     */
    public function applyQueryCallback(Builder $query, Model $user): Builder
    {
        return ($this->queryCallback)($query, $user);
    }

    /**
     * Executes the allowed callback to check user access.
     *
     * @param Model $user The user model instance to evaluate for access.
     *
     * @return bool True if the user is allowed; false otherwise.
     */
    public function applyAllowedCallback(Model $user): bool
    {
        return ($this->allowedCallback)($user);
    }

    /**
     * Sets the allowed callback for permission checks.
     *
     * @param Closure $allowedCallback A callback that performs the permission evaluation.
     *
     * @return self Returns the current instance.
     */
    public function allowed(Closure $allowedCallback): self
    {
        $this->allowedCallback = $allowedCallback;

        return $this;
    }

    /**
     * Sets the callback used to determine if a specific access control condition should be applied.
     *
     * @param Closure $shouldCallback A callback that returns a boolean based on custom logic.
     *
     * @return self The current instance.
     */
    public function should(Closure $shouldCallback): self
    {
        $this->shouldCallback = $shouldCallback;

        return $this;
    }

    /**
     * Sets a custom callback to modify Eloquent query builders for access control.
     *
     * @param Closure $queryCallback Callback that receives and returns a query builder.
     *
     * @return self The current Perimeter instance.
     */
    public function query(Closure $queryCallback): self
    {
        $this->queryCallback = $queryCallback;

        return $this;
    }

    /**
     * Sets the callback used to modify Laravel Scout search queries for this perimeter.
     *
     * @param Closure $scoutQueryCallback Callback that receives a Scout query builder and user model, and returns a modified query builder.
     *
     * @return self
     */
    public function scoutQuery(Closure $scoutQueryCallback): self
    {
        $this->scoutQueryCallback = $scoutQueryCallback;

        return $this;
    }

    /**
     * Creates and returns a new instance of the Perimeter class.
     *
     * @return static A new instance of the current class.
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Determines whether this Perimeter instance supports overlay functionality with other perimeters.
     *
     * @return bool
     */
    public function overlays(): bool
    {
        return false;
    }
}
