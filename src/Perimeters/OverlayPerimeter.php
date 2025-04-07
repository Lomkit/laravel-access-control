<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * Indicates that overlays are enabled.
     *
     * Always returns true.
     *
     * @return bool Always true.
     */
    public function overlays(): bool
    {
        return true;
    }
}
