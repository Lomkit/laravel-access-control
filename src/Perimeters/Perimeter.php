<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;

/** @phpstan-consistent-constructor */
class Perimeter
{
    protected ?Closure $scoutQueryCallback = null;

    protected ?Closure $queryCallback = null;

    protected ?Closure $shouldCallback = null;

    protected ?Closure $allowedCallback = null;

    private bool $unrestricted = false;

    /**
     * @throws MissingPerimeterCallbackException If the perimeter does not declare a should callback.
     */
    public function applyShouldCallback(Model $user, Model $model): bool
    {
        /** @var Closure(Model, Model): bool $callback */
        $callback = $this->shouldCallback ?? throw MissingPerimeterCallbackException::for($this, 'should');

        return $callback($user, $model);
    }

    /**
     * @template TModel of Model
     *
     * @param  \Laravel\Scout\Builder<TModel>  $query
     * @return \Laravel\Scout\Builder<TModel> The builder the callback returned, or the given one when it returned nothing.
     *
     * @throws MissingPerimeterCallbackException If the perimeter does not declare a Scout query callback.
     */
    public function applyScoutQueryCallback(\Laravel\Scout\Builder $query, Model $user): \Laravel\Scout\Builder
    {
        /** @var Closure(\Laravel\Scout\Builder<TModel>, Model): (\Laravel\Scout\Builder<TModel>|null) $callback */
        $callback = $this->scoutQueryCallback ?? throw MissingPerimeterCallbackException::for($this, 'scoutQuery');

        return $callback($query, $user) ?? $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel> The builder the callback returned, or the given one when it returned nothing.
     *
     * @throws MissingPerimeterCallbackException If the perimeter does not declare a query callback.
     */
    public function applyQueryCallback(Builder $query, Model $user): Builder
    {
        /** @var Closure(Builder<TModel>, Model): (Builder<TModel>|null) $callback */
        $callback = $this->queryCallback ?? throw MissingPerimeterCallbackException::for($this, 'query');

        return $callback($query, $user) ?? $query;
    }

    /**
     * @throws MissingPerimeterCallbackException If the perimeter does not declare an allowed callback.
     */
    public function applyAllowedCallback(Model $user, Ability $ability): bool
    {
        /** @var Closure(Model, Ability): bool $callback */
        $callback = $this->allowedCallback ?? throw MissingPerimeterCallbackException::for($this, 'allowed');

        return $callback($user, $ability);
    }

    public function allowed(Closure $allowedCallback): self
    {
        $this->allowedCallback = $allowedCallback;

        return $this;
    }

    /**
     * @throws UnrestrictedPerimeterCallbackException If the perimeter is unrestricted.
     */
    public function should(Closure $shouldCallback): self
    {
        $this->ensureRestricted('should');

        $this->shouldCallback = $shouldCallback;

        return $this;
    }

    /**
     * @throws UnrestrictedPerimeterCallbackException If the perimeter is unrestricted.
     */
    public function query(Closure $queryCallback): self
    {
        $this->ensureRestricted('query');

        $this->queryCallback = $queryCallback;

        return $this;
    }

    /**
     * @throws UnrestrictedPerimeterCallbackException If the perimeter is unrestricted.
     */
    public function scoutQuery(Closure $scoutQueryCallback): self
    {
        $this->ensureRestricted('scoutQuery');

        $this->scoutQueryCallback = $scoutQueryCallback;

        return $this;
    }

    /**
     * @throws UnrestrictedPerimeterCallbackException If the perimeter already declares a row callback.
     */
    public function unrestricted(): self
    {
        $declared = array_filter([
            'should' => $this->shouldCallback,
            'query' => $this->queryCallback,
            'scoutQuery' => $this->scoutQueryCallback,
        ]);

        if ($declared !== []) {
            throw UnrestrictedPerimeterCallbackException::for($this, (string) array_key_first($declared));
        }

        $this->unrestricted = true;

        return $this;
    }

    final public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    public static function new(): static
    {
        return new static;
    }

    public function overlays(): bool
    {
        return false;
    }

    private function ensureRestricted(string $callback): void
    {
        if ($this->unrestricted) {
            throw UnrestrictedPerimeterCallbackException::for($this, $callback);
        }
    }
}
