<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * Indicates that the overlay perimeter is active.
     *
     * @return bool Always true.
     */
    public function overlays(): bool
    {
        return true;
    }
}
