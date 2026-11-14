<?php

namespace Lomkit\Access\Registry;

use ReflectionClass;

/** @internal */
final class ControlVersion
{
    /** @param  class-string  $control */
    public static function of(string $control): int
    {
        $version = 0;

        foreach (self::declaring(new ReflectionClass($control)) as $class) {
            $file = $class->getFileName();
            $version = max($version, is_string($file) ? (int) filemtime($file) : 0);
        }

        return $version;
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    private static function declaring(ReflectionClass $class): array
    {
        $classes = [$class];

        foreach ($class->getTraits() as $trait) {
            array_push($classes, ...self::declaring($trait));
        }

        $parent = $class->getParentClass();

        return $parent === false ? $classes : [...$classes, ...self::declaring($parent)];
    }
}
