<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

final class PerimeterWithoutSearchKeysException extends LogicException
{
    public static function forIndex(Control $control, string $position, Perimeter $perimeter): self
    {
        return new self(sprintf(
            'The [%s] perimeter at %s of the [%s] control declares no search keys, so the documents of an index of your own would not carry the [%s] rows it grants, and its readers would not find them. Declare searchKeys() on the perimeter, even when it never grants view, or unrestricted() if it reaches every row.',
            $perimeter::class,
            $position,
            $control::class,
            $control->getModel(),
        ));
    }
}
