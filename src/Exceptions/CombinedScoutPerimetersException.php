<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class CombinedScoutPerimetersException extends LogicException
{
    /**
     * Create the exception for perimeters that would have to be combined in a Scout query.
     *
     * @param array<\Lomkit\Access\Perimeters\Perimeter> $perimeters
     */
    public static function for(Control $control, array $perimeters): static
    {
        return new static(sprintf(
            'The [%s] control applies the [%s] perimeters to a Scout query, but Scout cannot combine perimeters with "or". Use a single perimeter for search, or an Eloquent query.',
            $control::class,
            implode(', ', array_map(fn (Perimeter $perimeter) => $perimeter::class, $perimeters)),
        ));
    }
}
