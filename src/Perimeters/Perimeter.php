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
     * Executes the "should" callback to determine if the access control condition is met.
     *
     * Invokes the callback set via the `should` method with the provided user, method, and model,
     * then returns the resulting boolean value.
     *
     * @param Model  $user   The user model to evaluate.
     * @param string $method The operation method to check.
     * @param Model  $model  The model instance on which to perform the check.
     *
     * @return bool The outcome of the should callback evaluation.
     */
    public function applyShouldCallback(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

    /**
     * Applies the query callback to modify the provided query builder based on user access.
     *
     * Executes the stored query callback closure with the given query builder and user model,
     * returning a modified query builder instance for further query customization.
     *
     * @param Builder $query The query builder to modify.
     * @param Model   $user  The user model influencing query modifications.
     *
     * @return Builder The modified query builder.
     */
    public function applyQueryCallback(Builder $query, Model $user): Builder
    {
        return ($this->queryCallback)($query, $user);
    }

    /**
     * Executes the allowed callback to determine if the specified user is permitted.
     *
     * This method invokes the callback set via the allowed() method, passing the provided user model
     * to check if the corresponding action is allowed.
     *
     * @param Model $user The user model instance to evaluate.
     *
     * @return bool True if the user is allowed; false otherwise.
     */
    public function applyAllowedCallback(Model $user): bool
    {
        return ($this->allowedCallback)($user);
    }

    /**
     * Set the "allowed" callback.
     *
     * Assigns a callback that determines if a user is permitted to perform a specific action, and returns the current instance for method chaining.
     *
     * @param Closure $allowedCallback The callback used to evaluate access permissions.
     *
     * @return self The current instance.
     */
    public function allowed(Closure $allowedCallback): self
    {
        $this->allowedCallback = $allowedCallback;

        return $this;
    }

    /**
     * Sets the callback used to determine if the access control condition should be applied.
     *
     * This callback is later invoked to assess whether specific access rules need to be enforced.
     *
     * @param Closure $shouldCallback A callable that evaluates access conditions.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function should(Closure $shouldCallback): self
    {
        $this->shouldCallback = $shouldCallback;

        return $this;
    }

    /**
     * Sets the query callback closure used for modifying the query builder.
     *
     * This method assigns the provided callback for use in access control-based query adjustments and returns the current instance to enable method chaining.
     *
     * @param Closure $queryCallback The callback that alters the query builder.
     *
     * @return self The current Perimeter instance.
     */
    public function query(Closure $queryCallback): self
    {
        $this->queryCallback = $queryCallback;

        return $this;
    }

    /**
     * Creates and returns a new instance of the Perimeter class.
     *
     * Utilizes late static binding to allow for flexible instantiation.
     *
     * @return static A new Perimeter instance.
     */
    public static function new()
    {
        return new static();
    }

    /**
     * Determines if this Perimeter supports overlaying with other Perimeters.
     *
     * This implementation always returns false, indicating that overlay functionality is disabled.
     *
     * @return bool Always false.
     */
    public function overlays(): bool
    {
        return false;
    }
}
