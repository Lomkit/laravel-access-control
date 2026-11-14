<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use ReflectionClass;
use ReflectionFunction;

final readonly class ClosureShape
{
    private const int NESTED_CLOSURES_COMPARED = 3;

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

        return $depth >= self::NESTED_CLOSURES_COMPARED || self::same($this->captured, $other->captured, $depth);
    }

    private static function same(mixed $value, mixed $other, int $depth): bool
    {
        if ($value instanceof Closure && $other instanceof Closure) {
            return self::of($value)->matches(self::of($other), $depth + 1);
        }

        if (is_array($value) && is_array($other)) {
            return self::sameArrays($value, $other, $depth);
        }

        if (is_object($value) && is_object($other) && $value::class === $other::class && (new ReflectionClass($value))->isReadOnly()) {
            return $value == $other;
        }

        return $value === $other;
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
