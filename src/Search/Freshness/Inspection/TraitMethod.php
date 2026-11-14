<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use ReflectionMethod;

/** @internal */
final class TraitMethod
{
    public static function isOverridden(string $class, string $method, string $trait): bool
    {
        $declared = new ReflectionMethod($class, $method);
        $default = new ReflectionMethod($trait, $method);

        return $declared->getFileName() !== $default->getFileName()
            || $declared->getStartLine() !== $default->getStartLine();
    }

    public static function mentions(string $class, string $method, string $name): bool
    {
        $declared = new ReflectionMethod($class, $method);
        $file = $declared->getFileName();
        $start = $declared->getStartLine();
        $end = $declared->getEndLine();
        $lines = is_string($file) && is_file($file) ? file($file) : false;

        if ($lines === false || $start === false || $end === false) {
            return true;
        }

        foreach (token_get_all('<?php '.implode('', array_slice($lines, $start - 1, $end - $start + 1))) as $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === $name) {
                return true;
            }
        }

        return false;
    }
}
