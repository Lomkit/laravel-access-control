<?php

namespace Lomkit\Access\Perimeters;

use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final readonly class ParameterType
{
    private ?ReflectionType $type;

    private function __construct(private ?ReflectionParameter $parameter)
    {
        $this->type = $parameter?->getType();
    }

    public static function at(ReflectionFunction $function, int $position): self
    {
        $parameters = $function->getParameters();
        $parameter = $parameters[$position] ?? null;

        if ($parameter === null && $parameters !== []) {
            $last = $parameters[array_key_last($parameters)];
            $parameter = $last->isVariadic() ? $last : null;
        }

        return new self($parameter);
    }

    public function parameter(): ?ReflectionParameter
    {
        return $this->parameter;
    }

    public function isTypedFor(object $value): bool
    {
        return $this->type !== null && self::typeAdmits($this->type, $value);
    }

    public function admits(object|string $value): bool
    {
        return $this->type === null || self::typeAdmits($this->type, $value);
    }

    private static function typeAdmits(ReflectionType $type, object|string $value): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return self::admittedByOneOf($type->getTypes(), $value);
        }

        if ($type instanceof ReflectionIntersectionType) {
            return self::admittedByEachOf($type->getTypes(), $value);
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        return match ($type->getName()) {
            'mixed', 'object' => true,
            default => is_a($value, $type->getName(), true),
        };
    }

    /** @param  array<ReflectionType>  $types */
    private static function admittedByOneOf(array $types, object|string $value): bool
    {
        foreach ($types as $type) {
            if (self::typeAdmits($type, $value)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<ReflectionType>  $types */
    private static function admittedByEachOf(array $types, object|string $value): bool
    {
        foreach ($types as $type) {
            if (! self::typeAdmits($type, $value)) {
                return false;
            }
        }

        return true;
    }
}
