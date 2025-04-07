<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * A perimeter overlays if he collides with other perimeters.
     *
     * @return bool
     */
    public function overlays(): bool
    {
        return true;
    }
}
