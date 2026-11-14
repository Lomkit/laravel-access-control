<?php

namespace Lomkit\Access\Search;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use ReflectionFunction;

final class ClosureSource
{
    public static function of(Closure $closure): string
    {
        $function = new ReflectionFunction($closure);

        return self::code($function).serialize(self::describe($function->getStaticVariables()));
    }

    private static function code(ReflectionFunction $function): string
    {
        $file = $function->getFileName();
        $start = $function->getStartLine();
        $end = $function->getEndLine();
        $lines = is_string($file) && is_file($file) ? file($file) : false;

        if ($lines === false || $start === false || $end === false) {
            return $function->getName();
        }

        return implode('', array_slice($lines, $start - 1, $end - $start + 1));
    }

    private static function describe(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map(self::describe(...), $value),
            $value instanceof Closure => self::of($value),
            $value instanceof BackedEnum => [$value::class, $value->value],
            $value instanceof Model => [$value::class, $value->getKey()],
            is_object($value) => $value::class,
            default => $value,
        };
    }
}
