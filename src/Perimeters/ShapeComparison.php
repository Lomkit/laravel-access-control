<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use UnitEnum;

/** @internal */
final class ShapeComparison
{
    private const string INSTANT = 'U.u e';

    /** @var array<string, true> */
    private array $visited = [];

    public static function comparesProperties(object $value): bool
    {
        return $value instanceof Perimeter || (new ReflectionClass($value))->isReadOnly();
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $others
     */
    public function sameArrays(array $values, array $others, int $depth): bool
    {
        if (array_keys($values) !== array_keys($others)) {
            return false;
        }

        foreach ($values as $key => $value) {
            if (! $this->same($value, $others[$key], $depth)) {
                return false;
            }
        }

        return true;
    }

    private function same(mixed $value, mixed $other, int $depth): bool
    {
        if (is_object($value) && is_object($other)) {
            return $value::class === $other::class && $this->sameObjects($value, $other, $depth);
        }

        if (is_array($value) && is_array($other)) {
            return $this->sameArrays($value, $other, $depth);
        }

        return $value === $other;
    }

    private function sameObjects(object $value, object $other, int $depth): bool
    {
        return match (true) {
            $value instanceof Closure && $other instanceof Closure => ClosureShape::of($value)->matchesWithin(ClosureShape::of($other), $this, $depth + 1),
            $value instanceof Model && $other instanceof Model => $value->is($other),
            $value instanceof UnitEnum => $value === $other,
            $value instanceof DateTimeInterface && $other instanceof DateTimeInterface => $value->format(self::INSTANT) === $other->format(self::INSTANT),
            $value instanceof Collection && $other instanceof Collection => ! $this->firstVisit($value, $other, $depth) || $this->sameArrays($value->all(), $other->all(), $depth),
            self::comparesProperties($value) => ! $this->firstVisit($value, $other, $depth) || $this->sameArrays(ObjectProperties::of($value), ObjectProperties::of($other), $depth),
            default => true,
        };
    }

    private function firstVisit(object $value, object $other, int $depth): bool
    {
        $pair = $depth.':'.spl_object_id($value).':'.spl_object_id($other);

        if (isset($this->visited[$pair])) {
            return false;
        }

        $this->visited[$pair] = true;

        return true;
    }
}
