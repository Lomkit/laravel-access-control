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
     * Executes the should callback to evaluate if an action should be applied.
     *
     * Invokes the stored closure using the provided user, action method name, and target model,
     * returning a boolean result based on the evaluation.
     *
     * @param Model $user The user to be evaluated.
     * @param string $method The name of the action triggering the callback.
     * @param Model $model The model instance involved in the evaluation.
     *
     * @return bool The result from the callback indicating whether the condition is met.
     */
    public function applyShouldCallback(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

    /**
     * Applies the query callback to modify the query builder based on the user context.
     *
     * This method invokes a pre-configured callback that customizes the query builder according to
     * the provided user model, enabling dynamic query modifications for access control.
     *
     * @param Builder $query The query builder instance to be modified.
     * @param Model $user The user model used to determine query modifications.
     * @return Builder The modified query builder.
     */
    public function applyQueryCallback(Builder $query, Model $user): Builder
    {
        return ($this->queryCallback)($query, $user);
    }

    /**
     * Executes the allowed callback to determine if the given user is permitted.
     *
     * @param Model $user The user model instance to check.
     * @return bool True if the callback allows access, otherwise false.
     */
    public function applyAllowedCallback(Model $user): bool
    {
        return ($this->allowedCallback)($user);
    }

    /**
     * Sets the callback used to determine if a user is allowed to perform an action.
     *
     * This method assigns a callback that evaluates user permissions and returns the current
     * instance to facilitate method chaining.
     *
     * @param Closure $allowedCallback A callback that checks and returns whether a user is permitted.
     * @return self The current instance for chained method calls.
     */
    public function allowed(Closure $allowedCallback): self
    {
        $this->allowedCallback = $allowedCallback;

        return $this;
    }

    /**
     * Sets the callback used to determine if a specific access condition should be applied.
     *
     * The provided callback is stored and executed later to evaluate whether access control rules should be enforced.
     *
     * @param Closure $shouldCallback The callback to assess if a condition is met.
     * @return self The current instance for method chaining.
     */
    public function should(Closure $shouldCallback): self
    {
        $this->shouldCallback = $shouldCallback;

        return $this;
    }

    /**
     * Sets the callback used to modify a query builder for access control.
     *
     * This callback is invoked by applyQueryCallback() to tailor query conditions based on the user's access.
     *
     * @param Closure $queryCallback The callback that modifies the query builder.
     * @return self Returns the current instance for method chaining.
     */
    public function query(Closure $queryCallback): self
    {
        $this->queryCallback = $queryCallback;

        return $this;
    }

    /**
     * Creates a new Perimeter instance.
     *
     * This static factory method returns a new instance of the Perimeter class.
     *
     * @return static A new Perimeter instance.
     */
    public static function new()
    {
        return new static();
    }

    /**
     * Determines whether this Perimeter overlays with others.
     *
     * This method always returns false, indicating that the Perimeter does not support overlays.
     *
     * @return bool False, as overlays are not supported.
     */
    public function overlays(): bool
    {
        return false;
    }
}
