<?php

namespace Lomkit\Access\Perimeters;

use ReflectionClass;
use ReflectionProperty;

final readonly class PerimeterState
{
    private const string UNINITIALIZED = "\0uninitialized";

    private const array PACKAGE_CLASSES = [Perimeter::class, ThroughParent::class];

    /** @param  array<string, mixed>  $properties */
    private function __construct(private array $properties) {}

    public static function of(Perimeter $perimeter): self
    {
        $properties = [];
        $class = new ReflectionClass($perimeter);

        while ($class instanceof ReflectionClass && ! in_array($class->getName(), self::PACKAGE_CLASSES, true)) {
            foreach ($class->getProperties() as $property) {
                if (! $property->isStatic() && $property->getDeclaringClass()->getName() === $class->getName()) {
                    $properties["{$class->getName()}::\${$property->getName()}"] = self::valueOf($property, $perimeter);
                }
            }

            $class = $class->getParentClass();
        }

        return new self($properties);
    }

    public function matches(self $other): bool
    {
        return ClosureShape::sameValues($this->properties, $other->properties);
    }

    private static function valueOf(ReflectionProperty $property, Perimeter $perimeter): mixed
    {
        return $property->isInitialized($perimeter) ? $property->getValue($perimeter) : self::UNINITIALIZED;
    }
}
