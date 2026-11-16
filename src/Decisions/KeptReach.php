<?php

namespace Lomkit\Access\Decisions;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ReachedPerimeters;

/** @internal */
final readonly class KeptReach
{
    public function __construct(
        private Control $control,
        private ReachedPerimeters $perimeters,
    ) {}

    public function for(Control $control): ?ReachedPerimeters
    {
        return $control === $this->control ? $this->perimeters : null;
    }
}
