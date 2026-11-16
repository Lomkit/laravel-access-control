<?php

namespace Lomkit\Access\Console\Check;

use Composer\Autoload\ClassLoader;
use ReflectionClass;

/** @internal */
final class ApplicationClass
{
    public static function is(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $file = (new ReflectionClass($class))->getFileName();

        return $file !== false
            && ! str_starts_with($file, self::vendorDirectory())
            && ! str_starts_with($file, dirname(__DIR__, 2).DIRECTORY_SEPARATOR);
    }

    private static function vendorDirectory(): string
    {
        return dirname((string) (new ReflectionClass(ClassLoader::class))->getFileName(), 2).DIRECTORY_SEPARATOR;
    }
}
