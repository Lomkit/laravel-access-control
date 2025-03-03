<?php

namespace Lomkit\Access\Perimeters;

class OverlayPerimeter extends Perimeter
{
    public function overlays(): bool
    {
        return true;
    }
}
