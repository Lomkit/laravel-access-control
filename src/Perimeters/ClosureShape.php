<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionFunction;
use UnitEnum;

final readonly class ClosureShape
{
    private const int NESTED_VALUES_COMPARED = 3;

    /** @param  array<array-key, mixed>  $captured */
    private function __construct(
        private string $location,
        private array $captured,
    ) {}

    public static function of(Closure $closure): self
    {
        $function = new ReflectionFunction($closure);

        return new self(
            sprintf('%s:%s:%d-%d', $function->getFileName() ?: 'internal', $function->getName(), $function->getStartLine() ?: 0, $function->getEndLine() ?: 0),
            $function->getStaticVariables(),
        );
    }

    public function matches(self $other, int $depth = 0): bool
    {
        if ($this->location !== $other->location) {
            return false;
        }

        return $depth >= self::NESTED_VALUES_COMPARED || self::same($this->captured, $other->captured, $depth);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $others
     */
    public static function sameValues(array $values, array $others): bool
    {
        return self::sameArrays($values, $others, 0);
    }

    private static function same(mixed $value, mixed $other, int $depth): bool
    {
        if (is_object($value) && is_object($other)) {
            return $value::class === $other::class && self::sameObjects($value, $other, $depth);
        }

        if (is_array($value) && is_array($other)) {
            return self::sameArrays($value, $other, $depth);
        }

        return $value === $other;
    }

    private static function sameObjects(object $value, object $other, int $depth): bool
    {
        return match (true) {
            $value instanceof Closure && $other instanceof Closure => self::of($value)->matches(self::of($other), $depth + 1),
            $value instanceof Model && $other instanceof Model => $value->is($other),
            $value instanceof UnitEnum => $value === $other,
            (new ReflectionClass($value))->isReadOnly() => self::sameReadonlyObjects($value, $other, $depth + 1),
            default => true,
        };
    }

    private static function sameReadonlyObjects(object $value, object $other, int $depth): bool
    {
        return $depth >= self::NESTED_VALUES_COMPARED || self::sameArrays(ObjectProperties::of($value), ObjectProperties::of($other), $depth);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $others
     */
    private static function sameArrays(array $values, array $others, int $depth): bool
    {
        if (array_keys($values) !== array_keys($others)) {
            return false;
        }

        foreach ($values as $key => $value) {
            if (! self::same($value, $others[$key], $depth)) {
                return false;
            }
        }

        return true;
    }
}
