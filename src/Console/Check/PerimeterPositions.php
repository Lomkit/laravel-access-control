<?php

namespace Lomkit\Access\Console\Check;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;

/** @internal */
final class PerimeterPositions
{
    /** @return array<string, Perimeter> */
    public static function of(Control $control): array
    {
        $perimeters = [];

        foreach ($control->declaration()->perimeters()->entries() as $index => $entry) {
            $position = '#'.($index + 1);

            if (! $entry instanceof Levels) {
                $perimeters[$position] = $entry;

                continue;
            }

            foreach ($entry->all() as $level => $perimeter) {
                $perimeters["{$position}.".($level + 1)] = $perimeter;
            }
        }

        return $perimeters;
    }
}
