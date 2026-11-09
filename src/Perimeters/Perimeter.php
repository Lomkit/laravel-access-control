<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;

class Perimeter
{
    // No callback has a default: a callback the perimeter needs but does not declare throws instead of granting access
    protected ?Closure $scoutQueryCallback = null;

    protected ?Closure $queryCallback = null;

    protected ?Closure $shouldCallback = null;

    protected ?Closure $allowedCallback = null;

    /**
     * Determines if the access control condition should be applied for the given user, method, and model.
     *
     * @param  Model  $user  The user being evaluated.
     * @param  string  $method  The access control action or method.
     * @param  Model  $model  The related model instance.
     * @return bool True if the condition applies; false otherwise.
     *
     * @throws MissingPerimeterCallbackException If the perimeter does not declare a should callback.
     */
    public function applyShouldCallback(Model $user, Model $model): bool
    {
        $callback = $this->shouldCallback ?? throw MissingPerimeterCallbackException::for($this, 'should');

        return $callback($user, $model);
    }

    /**
     * Applies the configured Scout query callback to modify a Laravel Scout search query for a given user.
     *
     * @param  \Laravel\Scout\Builder  $query  The Scout query builder to modify.
     * @param  Model  $user  The user model for whom the query is being modified.
     * @return \Laravel\Scout\Builder The modified Scout query builder.
     *
     * @throws MissingPerimeterCallbackException If the perimeter does not declare a Scout query callback.
     */
    public function applyScoutQueryCallback(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        $callback = $this->scoutQueryCallback ?? throw MissingPerimeterCallbackException::for($this, 'scoutQuery');

        return $callback($query, $user);
    }

    /**
     * Applies the registered query callback to modify the query builder based on the user's context.
     *
     * @param  Builder  $query  The query builder instance to be customized.
     * @param  Model  $user  The user model providing context for the query modification.
     * @return Builder The modified query builder.
     *
     * @throws MissingPerimeterCallbackException If the perimeter does not declare a query callback.
     */
    public function applyQueryCallback(Builder $query, Model $user): Builder
    {
        $callback = $this->queryCallback ?? throw MissingPerimeterCallbackException::for($this, 'query');

        return $callback($query, $user);
    }

    /**
     * Executes the allowed callback to check user access.
     *
     * @param  Model  $user  The user model instance to evaluate for access.
     * @return bool True if the user is allowed; false otherwise.
     *
     * @throws MissingPerimeterCallbackException If the perimeter does not declare an allowed callback.
     */
    public function applyAllowedCallback(Model $user, string $method): bool
    {
        $callback = $this->allowedCallback ?? throw MissingPerimeterCallbackException::for($this, 'allowed');

        return $callback($user, $method);
    }

    /**
     * Sets the allowed callback for permission checks.
     *
     * @param  Closure  $allowedCallback  A callback that performs the permission evaluation.
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
     * @param  Closure  $shouldCallback  A callback that returns a boolean based on custom logic.
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
     * @param  Closure  $queryCallback  Callback that receives and returns a query builder.
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
     * @param  Closure  $scoutQueryCallback  Callback that receives a Scout query builder and user model, and returns a modified query builder.
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
        return new static;
    }

    /**
     * Determines whether this Perimeter instance supports overlay functionality with other perimeters.
     */
    public function overlays(): bool
    {
        return false;
    }
}
