<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Exceptions\OverriddenPerimeterHookException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;

/** @internal */
final readonly class PerimeterCallbacks
{
    /** @param  array<value-of<PerimeterHook>, PerimeterClosure>  $callbacks */
    private function __construct(private array $callbacks) {}

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @throws OverriddenPerimeterHookException If the perimeter overrides the hook the callback stands for.
     * @throws UnrestrictedPerimeterCallbackException If the callback checks rows on an unrestricted perimeter.
     * @throws AbilityParameterTypeException If the callback takes the ability in a parameter that does not accept an Ability.
     * @throws DuplicatePerimeterCallbackException If the perimeter already declares the callback.
     */
    public function declaring(PerimeterHook $hook, Closure $callback, Perimeter $perimeter): self
    {
        if ($hook->isOverriddenIn($perimeter::class)) {
            throw OverriddenPerimeterHookException::for($perimeter, $hook);
        }

        if ($this->declares($hook)) {
            throw DuplicatePerimeterCallbackException::for($perimeter, $hook->callback());
        }

        if ($perimeter->isUnrestricted() && $hook->checksRows()) {
            throw UnrestrictedPerimeterCallbackException::for($perimeter, $hook->callback());
        }

        return new self([...$this->callbacks, $hook->value => PerimeterClosure::of($callback, $hook, $perimeter)]);
    }

    public function declares(PerimeterHook $hook): bool
    {
        return array_key_exists($hook->value, $this->callbacks);
    }

    /** @throws MissingPerimeterCallbackException If the perimeter does not declare the callback. */
    public function for(PerimeterHook $hook, Perimeter $perimeter): PerimeterClosure
    {
        return $this->callbacks[$hook->value] ?? throw MissingPerimeterCallbackException::for($perimeter, $hook);
    }

    public function admit(Model $reader): bool
    {
        foreach ($this->callbacks as $callback) {
            if (! $callback->admits($reader)) {
                return false;
            }
        }

        return true;
    }

    /** @throws UnrestrictedPerimeterCallbackException If the perimeter declares or overrides a row check. */
    public function ensureNoRowCheck(Perimeter $perimeter): void
    {
        foreach (PerimeterHook::cases() as $hook) {
            if (! $hook->checksRows()) {
                continue;
            }

            if ($this->declares($hook)) {
                throw UnrestrictedPerimeterCallbackException::for($perimeter, $hook->callback());
            }

            if ($hook->isOverriddenIn($perimeter::class)) {
                throw UnrestrictedPerimeterCallbackException::overriding($perimeter, $hook);
            }
        }
    }

    /** @return array<value-of<PerimeterHook>, ClosureShape> */
    public function shapes(): array
    {
        return array_map(fn (PerimeterClosure $callback): ClosureShape => $callback->shape(), $this->callbacks);
    }
}
