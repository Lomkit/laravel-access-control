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
     * Executes the configured should callback using the provided user, method, and model.
     *
     * Invokes the callback set via the should() method to determine if a specific condition is met. Returns true if the condition passes, or false otherwise.
     *
     * @param Model $user The user instance for evaluation.
     * @param string $method The name of the operation or action triggering this check.
     * @param Model $model The model instance associated with the evaluation.
     * @return bool The boolean result of the callback's condition evaluation.
     */
    public function applyShouldCallback(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

    /**
     * Applies the configured query callback to modify the query builder based on the current user.
     *
     * Executes the query callback stored in the instance, passing the provided query builder and user model, and returns the modified query builder.
     *
     * @param Builder $query The query builder instance to be modified.
     * @param Model $user The user model providing context for the query modification.
     * @return Builder The modified query builder.
     */
    public function applyQueryCallback(Builder $query, Model $user): Builder
    {
        return ($this->queryCallback)($query, $user);
    }

    /**
     * Executes the allowed callback to determine if the user has access.
     *
     * This method invokes the allowed callback closure with the provided user model,
     * returning a boolean result that indicates whether the user is permitted access.
     *
     * @param Model $user The user instance to check for access permissions.
     * @return bool True if access is granted, false otherwise.
     */
    public function applyAllowedCallback(Model $user): bool
    {
        return ($this->allowedCallback)($user);
    }

    /**
     * Sets the callback to determine access permissions.
     *
     * This method assigns a closure that evaluates whether a user is allowed access,
     * enabling flexible permission checks. It returns the current instance to facilitate
     * method chaining.
     *
     * @param Closure $allowedCallback The callback function that assesses user access.
     * @return self The current instance of the perimeter.
     */
    public function allowed(Closure $allowedCallback): self
    {
        $this->allowedCallback = $allowedCallback;

        return $this;
    }

    /**
     * Sets the callback to determine if the access control condition should be enforced.
     *
     * @param Closure $shouldCallback A callback to evaluate whether the condition applies.
     * @return self The current instance for method chaining.
     */
    public function should(Closure $shouldCallback): self
    {
        $this->shouldCallback = $shouldCallback;

        return $this;
    }

    /**
     * Sets the query callback to modify the query builder.
     *
     * The provided closure is used to dynamically adjust the query builder based on access control logic.
     *
     * @param Closure $queryCallback A closure that modifies and returns a query builder instance.
     * @return self The current Perimeter instance for method chaining.
     */
    public function query(Closure $queryCallback): self
    {
        $this->queryCallback = $queryCallback;

        return $this;
    }

    /**
     * Creates and returns a new instance of the class using late static binding.
     *
     * This static factory method instantiates the class via "new static()", ensuring that any subclass
     * calling this method will receive an instance of its own type.
     *
     * @return static A new instance of the class.
     */
    public static function new()
    {
        return new static();
    }

    /**
     * Determines if this Perimeter instance overlays with others.
     *
     * This method always returns false, indicating that overlay functionality is not supported.
     *
     * @return bool Always false.
     */
    public function overlays(): bool
    {
        return false;
    }
}
