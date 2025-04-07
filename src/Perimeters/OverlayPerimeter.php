<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * Indicates that overlay functionality is active.
     *
     * This method always returns true, confirming that overlays are enabled.
     *
     * @return bool Always true.
     */
    public function overlays(): bool
    {
        return true;
    }
}
