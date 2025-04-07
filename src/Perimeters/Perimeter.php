<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Perimeter
{
    protected Closure $queryCallback;
    protected Closure $shouldCallback;

    protected Closure $allowedCallback;

    /**
     * Executes the should callback to determine if the access control condition is met.
     *
     * This method invokes the should callback closure using the provided user, method, and model
     * and returns a boolean value that indicates whether the associated access control condition holds.
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

    /**
     * Applies the registered query callback to modify the query builder based on the user's context.
     *
     * This method invokes a closure set via the `query()` method, passing the query builder and user model,
     * and returns the modified builder.
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
     * This method invokes the allowed callback closure with the provided user model,
     * returning a boolean value that indicates whether the user is permitted.
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
     * Assigns a closure to be used for determining whether a user is permitted to perform an action,
     * and returns the current instance to facilitate method chaining.
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
     * This method assigns a closure that is later used to dynamically evaluate whether
     * the associated rule or condition should be enforced, and returns the current instance
     * to facilitate method chaining.
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
     * Assigns the provided closure to modify query builder instances dynamically when processing user-related queries.
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

    /**
     * Creates and returns a new instance of the Perimeter class.
     *
     * @return static A new instance of the current class.
     */
    public static function new()
    {
        return new static();
    }

    /**
     * Determines whether this Perimeter instance supports overlay functionality with other perimeters.
     *
     * This implementation always returns false, signifying that overlaying behavior is not supported.
     *
     * @return bool Always false.
     */
    public function overlays(): bool
    {
        return false;
    }
}
