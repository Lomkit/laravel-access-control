<?php

namespace Lomkit\Access\Perimeters;

/** @internal */
final readonly class PerimeterState
{
    private const array PACKAGE_CLASSES = [Perimeter::class, ThroughParent::class];

    /** @param  array<string, mixed>  $properties */
    private function __construct(private array $properties) {}

    public static function of(Perimeter $perimeter): self
    {
        return new self(ObjectProperties::of($perimeter, self::PACKAGE_CLASSES));
    }

    public function matches(self $other): bool
    {
        return ClosureShape::sameValues($this->properties, $other->properties);
    }
}
