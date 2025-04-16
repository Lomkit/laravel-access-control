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

    public function __construct()
    {
        // Default implementations that can be overridden
        $this->scoutQueryCallback = function (\Laravel\Scout\Builder $query, Model $user) { return $query; };
        $this->queryCallback = function (Builder $query, Model $user) { return $query; };
        $this->shouldCallback = function (Model $user, string $method, Model $model) { return true; };
        $this->allowedCallback = function (Model $user) { return true; };
    }

    /**
     * Executes the should callback to determine if the access control condition is met.
     *
     * @param Model  $user   The user instance for which the check is performed.
     * @param string $method The access control method or action being evaluated.
     * @param Model  $model  The model instance related to the access check.
     *
     * @return bool True if the callback validation passes; otherwise, false.
     */
    public function applyShouldCallback(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

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
     * Sets the query modification callback.
     *
     * @param Closure $queryCallback A callback that customizes the query logic.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function query(Closure $queryCallback): self
    {
        $this->queryCallback = $queryCallback;

        return $this;
    }

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
