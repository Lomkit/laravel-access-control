<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * Indicates that overlays are active.
     *
     * This method always returns true, signaling that the overlay functionality is enabled.
     *
     * @return bool Always returns true.
     */
    public function overlays(): bool
    {
        return true;
    }
}
