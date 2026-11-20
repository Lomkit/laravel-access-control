<?php

namespace Lomkit\Access\Search;

use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;

/** @internal */
final class KeylessPerimeters
{
    /**
     * @param  array<string, Perimeter>  $positions
     * @return array<string, Perimeter>
     */
    public static function in(array $positions): array
    {
        return array_filter(
            $positions,
            fn (Perimeter $perimeter): bool => ! $perimeter->isUnrestricted() && ! $perimeter instanceof ThroughParent && $perimeter->declaredSearchKeys() === null,
        );
    }
}
