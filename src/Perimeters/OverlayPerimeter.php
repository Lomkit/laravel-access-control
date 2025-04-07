<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * Indicates that the overlay perimeter is active.
     *
     * This method always returns true, confirming that overlay functionality is enabled.
     *
     * @return bool Always true.
     */
    public function overlays(): bool
    {
        return true;
    }
}
