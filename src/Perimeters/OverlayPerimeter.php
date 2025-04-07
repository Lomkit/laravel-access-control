<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    /**
     * A perimeter overlays if he collides with other perimeters.
     * When true, this perimeter's rules can be combined with other perimeters.
     * When false, this perimeter's rules will be applied independently and can override other perimeters.
     *
     * @return bool
     */
    public function overlays(): bool
    {
        return true;
    }
}
