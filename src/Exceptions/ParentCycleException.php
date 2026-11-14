<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class ParentCycleException extends LogicException
{
    /** @param  non-empty-list<Control>  $cycle */
    public static function through(array $cycle): self
    {
        return new self(sprintf(
            'The ThroughParent perimeters of the [%s] control form a cycle: %s. A row cannot inherit its access from itself: remove one ThroughParent of the cycle and give that model perimeters of its own.',
            $cycle[0]::class,
            implode(' → ', array_map(fn (Control $control): string => $control->getModel(), $cycle)),
        ));
    }
}
