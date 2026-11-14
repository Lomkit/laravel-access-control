<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class AbilityParameter
{
    /**
     * @throws AbilityParameterTypeException When the closure takes a second parameter that is not typed to receive an Ability.
     */
    public static function ensureTypedIn(Perimeter $perimeter, Closure $allowedCallback): void
    {
        $parameter = (new ReflectionFunction($allowedCallback))->getParameters()[1] ?? null;

        if ($parameter === null || self::receivesAbility($parameter)) {
            return;
        }

        throw AbilityParameterTypeException::forAllowedCallback($perimeter, $parameter);
    }

    /**
     * @throws AbilityParameterTypeException When the perimeter overrides the hook without typing its ability to receive an Ability.
     */
    public static function ensureTypedInHookOf(Perimeter $perimeter): void
    {
        $hook = new ReflectionMethod($perimeter, 'applyAllowedCallback');
        $parameter = $hook->getParameters()[1] ?? null;

        if ($parameter === null || self::receivesAbility($parameter)) {
            return;
        }

        throw AbilityParameterTypeException::forHook($perimeter, $parameter);
    }

    private static function receivesAbility(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type !== null && self::accepts($type);
    }

    private static function accepts(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return self::acceptedByOneOf($type->getTypes());
        }

        if ($type instanceof ReflectionIntersectionType) {
            return self::acceptedByEachOf($type->getTypes());
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        return in_array($type->getName(), ['mixed', 'object'], true) || is_a(Ability::class, $type->getName(), true);
    }

    /** @param  array<ReflectionType>  $types */
    private static function acceptedByOneOf(array $types): bool
    {
        foreach ($types as $type) {
            if (self::accepts($type)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<ReflectionType>  $types */
    private static function acceptedByEachOf(array $types): bool
    {
        foreach ($types as $type) {
            if (! self::accepts($type)) {
                return false;
            }
        }

        return true;
    }
}
