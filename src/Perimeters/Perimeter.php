<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use Lomkit\Access\Exceptions\InvalidSearchKeyNameException;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Exceptions\OverriddenPerimeterHookException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Search\SearchKeys;

/** @phpstan-consistent-constructor */
class Perimeter
{
    /** @var array<value-of<PerimeterHook>, PerimeterClosure> */
    private array $callbacks = [];

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
     */
    public function searchKeys(string $name, Closure $row, Closure $reader, array $load = []): static
    {
        $perimeter = clone $this;
        $perimeter->searchKeys = SearchKeys::declaredBy($this, $name, $row, $reader, $load);

        return $perimeter;
    }

    /**
     * @throws UnrestrictedPerimeterCallbackException If the perimeter declares or overrides a row check, or declares search keys.
     */
    public function unrestricted(): static
    {
        if ($this->searchKeys !== null) {
            throw UnrestrictedPerimeterCallbackException::for($this, 'searchKeys');
        }

        foreach (PerimeterHook::cases() as $hook) {
            if (! $hook->checksRows()) {
                continue;
            }

            if (array_key_exists($hook->value, $this->callbacks)) {
                throw UnrestrictedPerimeterCallbackException::for($this, $hook->callback());
            }

            if ($hook->isOverriddenIn(static::class)) {
                throw UnrestrictedPerimeterCallbackException::overriding($this, $hook);
            }
        }

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
            || array_key_exists(PerimeterHook::Includes->value, $this->callbacks)
            || PerimeterHook::Includes->isOverriddenIn(static::class);
    }

    final public function admits(Model $reader, Ability $ability, Model $row): bool
    {
        $context = AccessContext::of($reader, $ability);

        return $this->applyAllowedCallback($context) && $this->applyShouldCallback($row, $context);
    }

    /** @internal */
    final public function applyAllowedCallback(AccessContext $context): bool
    {
        if ($this->searchKeys?->admits($context->reader()) === false) {
            return false;
        }

        foreach ($this->callbacks as $callback) {
            if (! $callback->admits($context->reader())) {
                return false;
            }
        }

        return $this->allows($context);
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
        return new PerimeterShape(
            static::class,
            $this->unrestricted,
            array_map(fn (PerimeterClosure $callback): ClosureShape => $callback->shape(), $this->callbacks),
            $this->searchKeys,
        );
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

    /**
     * @throws OverriddenPerimeterHookException If the perimeter overrides the hook the callback stands for.
     * @throws UnrestrictedPerimeterCallbackException If the callback checks rows on an unrestricted perimeter.
     * @throws AbilityParameterTypeException If the callback takes the ability in a parameter that does not accept an Ability.
     */
    private function declaring(PerimeterHook $hook, Closure $callback): static
    {
        if ($hook->isOverriddenIn(static::class)) {
            throw OverriddenPerimeterHookException::for($this, $hook);
        }

        if ($this->unrestricted && $hook->checksRows()) {
            throw UnrestrictedPerimeterCallbackException::for($this, $hook->callback());
        }

        $perimeter = clone $this;
        $perimeter->callbacks[$hook->value] = PerimeterClosure::of($callback, $hook, $this);

        return $perimeter;
    }

    /** @throws MissingPerimeterCallbackException If the perimeter does not declare the callback. */
    private function callback(PerimeterHook $hook): PerimeterClosure
    {
        return $this->callbacks[$hook->value] ?? throw MissingPerimeterCallbackException::for($this, $hook);
    }
}
