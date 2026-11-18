<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionFunction;
use UnitEnum;

final readonly class ClosureShape
{
    private const int NESTED_CLOSURES_COMPARED = 3;

    private const string INSTANT = 'U.u e';

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

        return $depth >= self::NESTED_CLOSURES_COMPARED || self::same($this->captured, $other->captured, $depth, []);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $others
     */
    public static function sameValues(array $values, array $others): bool
    {
        return self::sameArrays($values, $others, 0, []);
    }

    /** @param  array<string, true>  $comparing */
    private static function same(mixed $value, mixed $other, int $depth, array $comparing): bool
    {
        if (is_object($value) && is_object($other)) {
            return $value::class === $other::class && self::sameObjects($value, $other, $depth, $comparing);
        }

        if (is_array($value) && is_array($other)) {
            return self::sameArrays($value, $other, $depth, $comparing);
        }

        return $value === $other;
    }

    /** @param  array<string, true>  $comparing */
    private static function sameObjects(object $value, object $other, int $depth, array $comparing): bool
    {
        return match (true) {
            $value instanceof Closure && $other instanceof Closure => self::of($value)->matches(self::of($other), $depth + 1),
            $value instanceof Model && $other instanceof Model => $value->is($other),
            $value instanceof UnitEnum => $value === $other,
            $value instanceof DateTimeInterface && $other instanceof DateTimeInterface => $value->format(self::INSTANT) === $other->format(self::INSTANT),
            $value instanceof Perimeter && $other instanceof Perimeter => self::samePerimeters($value, $other, $depth, $comparing),
            (new ReflectionClass($value))->isReadOnly() => self::sameReadonlyObjects($value, $other, $depth, $comparing),
            default => true,
        };
    }

    /** @param  array<string, true>  $comparing */
    private static function samePerimeters(Perimeter $value, Perimeter $other, int $depth, array $comparing): bool
    {
        $pair = self::pair($value, $other);

        if (isset($comparing[$pair])) {
            return true;
        }

        if ($value instanceof ThroughParent && $other instanceof ThroughParent && ! $value->sameDeclarationAs($other)) {
            return false;
        }

        return self::sameReadonlyObjects($value->shape(), $other->shape(), $depth, [...$comparing, $pair => true]);
    }

    /** @param  array<string, true>  $comparing */
    private static function sameReadonlyObjects(object $value, object $other, int $depth, array $comparing): bool
    {
        $pair = self::pair($value, $other);

        if (isset($comparing[$pair])) {
            return true;
        }

        return self::sameArrays(ObjectProperties::of($value), ObjectProperties::of($other), $depth, [...$comparing, $pair => true]);
    }

    private static function pair(object $value, object $other): string
    {
        return spl_object_id($value).':'.spl_object_id($other);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $others
     * @param  array<string, true>  $comparing
     */
    private static function sameArrays(array $values, array $others, int $depth, array $comparing): bool
    {
        if (array_keys($values) !== array_keys($others)) {
            return false;
        }

        foreach ($values as $key => $value) {
            if (! self::same($value, $others[$key], $depth, $comparing)) {
                return false;
            }
        }

        return true;
    }
}
