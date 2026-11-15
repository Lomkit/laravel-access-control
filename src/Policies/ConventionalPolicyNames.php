<?php

namespace Lomkit\Access\Policies;

use Closure;
use Illuminate\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use ReflectionFunction;

/** @internal */
final class ConventionalPolicyNames extends Gate
{
    /** @return Closure(string): array<array-key, mixed> */
    public static function of(Gate $gate, Container $container): Closure
    {
        $guess = $gate->guessPolicyNamesUsingCallback;

        if (is_callable($guess)) {
            return fn (string $class): array => Arr::wrap($guess($class));
        }

        $conventional = new self($container, fn (): null => null);

        return fn (string $class): array => $conventional->guessPolicyName($class);
    }

    public static function guessedIn(Gate $gate, string $scope): bool
    {
        $guess = $gate->guessPolicyNamesUsingCallback;

        return $guess instanceof Closure && (new ReflectionFunction($guess))->getClosureScopeClass()?->getName() === $scope;
    }
}
