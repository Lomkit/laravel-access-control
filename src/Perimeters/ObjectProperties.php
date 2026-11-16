<?php

namespace Lomkit\Access\Perimeters;

use ReflectionClass;

/** @internal */
final class ObjectProperties
{
    private const string UNINITIALIZED = "\0uninitialized";

    /**
     * @param  list<class-string>  $ignored
     * @return array<string, mixed>
     */
    public static function of(object $object, array $ignored = []): array
    {
        $properties = [];
        $class = new ReflectionClass($object);

        while ($class instanceof ReflectionClass && ! in_array($class->getName(), $ignored, true)) {
            foreach ($class->getProperties() as $property) {
                if (! $property->isStatic() && $property->getDeclaringClass()->getName() === $class->getName()) {
                    $properties["{$class->getName()}::\${$property->getName()}"] = $property->isInitialized($object) ? $property->getValue($object) : self::UNINITIALIZED;
                }
            }

            $class = $class->getParentClass();
        }

        return $properties;
    }
}
