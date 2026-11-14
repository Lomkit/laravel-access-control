<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Lomkit\Access\Ability;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

enum AbilityArgument
{
    case Instance;
    case Name;

    public static function expectedBy(Closure $callback): self
    {
        return self::expectedByParameter((new ReflectionFunction($callback))->getParameters()[1] ?? null);
    }

    public static function expectedByHookOf(Perimeter $perimeter): self
    {
        $hook = new ReflectionMethod($perimeter, 'applyAllowedCallback');

        if ($hook->getDeclaringClass()->getName() === Perimeter::class) {
            return self::Instance;
        }

        return self::expectedByParameter($hook->getParameters()[1] ?? null);
    }

    public function of(Ability $ability): Ability|string
    {
        return match ($this) {
            self::Instance => $ability,
            self::Name => $ability->name(),
        };
    }

    private static function expectedByParameter(?ReflectionParameter $parameter): self
    {
        $type = $parameter?->getType();

        if ($type === null || ! self::accepts($type)) {
            return self::Name;
        }

        return self::Instance;
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

        return $type->getName() === 'object' || is_a(Ability::class, $type->getName(), true);
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
