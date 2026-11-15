<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Exceptions\DuplicateReindexException;
use Lomkit\Access\Exceptions\InvalidReindexAttributesException;
use Lomkit\Access\Exceptions\InvalidReindexModelException;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Exceptions\InvalidSearchKeyNameException;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Exceptions\ReindexWithoutSearchKeysException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Search\Freshness\ReindexWhen;
use Lomkit\Access\Search\SearchKeys;

/** @phpstan-consistent-constructor */
class Perimeter
{
    private ?PerimeterCallbacks $callbacks = null;

    private bool $unrestricted = false;

    private ?SearchKeys $searchKeys = null;

    public static function new(): static
    {
        return new static;
    }

    public function allowed(Closure $callback): static
    {
        return $this->declaring(PerimeterHook::Allows, $callback);
    }

    public function should(Closure $callback): static
    {
        return $this->declaring(PerimeterHook::Includes, $callback);
    }

    public function query(Closure $callback): static
    {
        return $this->declaring(PerimeterHook::Scope, $callback);
    }

    /**
     * @param  list<string>  $load
     *
     * @throws InvalidSearchKeyNameException If the name is not made of lowercase letters, digits and underscores.
     * @throws UnrestrictedPerimeterCallbackException If the perimeter is unrestricted.
     * @throws DuplicatePerimeterCallbackException If the perimeter already declares search keys.
     */
    public function searchKeys(string $name, Closure $row, Closure $reader, array $load = []): static
    {
        $perimeter = clone $this;
        $perimeter->searchKeys = SearchKeys::declaredBy($this, $name, $row, $reader, $load);

        return $perimeter;
    }

    /**
     * @param  list<string>  $changed
     *
     * @throws ReindexWithoutSearchKeysException If the perimeter declares no search keys.
     * @throws InvalidReindexModelException If the model is not an Eloquent model class.
     * @throws InvalidReindexAttributesException If changed names no attribute, or a blank one.
     * @throws InvalidReindexRowsException If the rows callback does not accept the model.
     * @throws DuplicateReindexException If the perimeter already reindexes for the model.
     */
    public function reindexWhen(string $model, array $changed, Closure $rows): static
    {
        $perimeter = clone $this;
        $perimeter->searchKeys = ($this->searchKeys ?? throw ReindexWithoutSearchKeysException::for($this, $model))
            ->reindexingWhen(ReindexWhen::declaredBy($this, $model, $changed, $rows), $this);

        return $perimeter;
    }

    /** @throws UnrestrictedPerimeterCallbackException If the perimeter declares or overrides a row check, or declares search keys. */
    public function unrestricted(): static
    {
        if ($this->searchKeys !== null) {
            throw UnrestrictedPerimeterCallbackException::for($this, 'searchKeys');
        }

        $this->callbacks()->ensureNoRowCheck($this);

        $perimeter = clone $this;
        $perimeter->unrestricted = true;

        return $perimeter;
    }

    final public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    final public function checksInMemory(): bool
    {
        return $this->unrestricted
            || $this->callbacks()->declares(PerimeterHook::Includes)
            || PerimeterHook::Includes->isOverriddenIn(static::class);
    }

    final public function admits(Model $reader, Ability $ability, Model $row): bool
    {
        $context = AccessContext::of($reader, $ability);

        if (! $this->applyAllowedCallback($context)) {
            return false;
        }

        return $this->checksInMemory() ? $this->applyShouldCallback($row, $context) : StoredRowInQuery::contains($this, $row, $context);
    }

    /** @internal */
    final public function applyAllowedCallback(AccessContext $context): bool
    {
        if ($this->searchKeys?->admits($context->reader()) === false) {
            return false;
        }

        return $this->callbacks()->admit($context->reader()) && $this->allows($context);
    }

    /** @internal */
    final public function applyShouldCallback(Model $row, AccessContext $context): bool
    {
        return $this->unrestricted || $this->includes($row, $context);
    }

    /**
     * @internal
     *
     * @param  Builder<covariant Model>  $query
     */
    final public function applyQueryCallback(Builder $query, AccessContext $context): void
    {
        $this->scope($query, $context);
    }

    /** @internal */
    final public function declaredSearchKeys(): ?SearchKeys
    {
        return $this->searchKeys;
    }

    /** @internal */
    final public function shape(): PerimeterShape
    {
        return new PerimeterShape(static::class, $this->unrestricted, $this->callbacks()->shapes(), $this->searchKeys, PerimeterState::of($this));
    }

    protected function allows(AccessContext $context): bool
    {
        return $this->callback(PerimeterHook::Allows)->decide($this, $context->ability(), $context->reader());
    }

    protected function includes(Model $row, AccessContext $context): bool
    {
        return $this->callback(PerimeterHook::Includes)->decide($this, $context->ability(), $context->reader(), $row);
    }

    /** @param  Builder<covariant Model>  $query */
    protected function scope(Builder $query, AccessContext $context): void
    {
        $this->callback(PerimeterHook::Scope)->constrain($this, $context->ability(), $query, $context->reader());
    }

    private function declaring(PerimeterHook $hook, Closure $callback): static
    {
        $perimeter = clone $this;
        $perimeter->callbacks = $this->callbacks()->declaring($hook, $callback, $this);

        return $perimeter;
    }

    private function callbacks(): PerimeterCallbacks
    {
        return $this->callbacks ??= PerimeterCallbacks::none();
    }

    /** @throws MissingPerimeterCallbackException If the perimeter does not declare the callback. */
    private function callback(PerimeterHook $hook): PerimeterClosure
    {
        return $this->callbacks()->for($hook, $this);
    }
}
