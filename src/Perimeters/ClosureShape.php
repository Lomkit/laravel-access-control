<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use ReflectionFunction;

/** @internal */
final readonly class ClosureShape
{
    private const int NESTED_CLOSURES_COMPARED = 3;

    private const string BOUND_OBJECT = "\0this";

    /** @param  array<array-key, mixed>  $captured */
    private function __construct(
        private string $location,
        private array $captured,
    ) {}

    public static function of(Closure $closure): self
    {
        $function = new ReflectionFunction($closure);
        $captured = $function->getStaticVariables();
        $bound = $function->getClosureThis();

        if ($bound !== null && ShapeComparison::comparesProperties($bound)) {
            $captured[self::BOUND_OBJECT] = $bound;
        }

        return new self(
            sprintf('%s:%s:%d-%d', $function->getFileName() ?: 'internal', $function->getName(), $function->getStartLine() ?: 0, $function->getEndLine() ?: 0),
            $captured,
        );
    }

    public function matches(self $other): bool
    {
        return $this->matchesWithin($other, new ShapeComparison, 0);
    }

    /** @internal */
    public function matchesWithin(self $other, ShapeComparison $comparison, int $depth): bool
    {
        if ($this->location !== $other->location) {
            return false;
        }

        return $depth >= self::NESTED_CLOSURES_COMPARED || $comparison->sameArrays($this->captured, $other->captured, $depth);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $others
     */
    public static function sameValues(array $values, array $others): bool
    {
        return (new ShapeComparison)->sameArrays($values, $others, 0);
    }
}
