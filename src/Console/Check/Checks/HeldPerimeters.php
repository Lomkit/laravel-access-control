<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Support\Collection;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\ObjectProperties;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;

/** @internal */
final class HeldPerimeters
{
    public static function in(Perimeter $perimeter): bool
    {
        foreach (ObjectProperties::of($perimeter, [Perimeter::class, ThroughParent::class]) as $value) {
            foreach (self::members($value) as $member) {
                if ($member instanceof Perimeter || $member instanceof Levels) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<array-key, mixed> */
    private static function members(mixed $value): array
    {
        return match (true) {
            $value instanceof Collection => $value->all(),
            is_array($value) => $value,
            default => [$value],
        };
    }
}
