<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Routing\Route;

class Perimeters
{
    /**
     * The perimeter collection instance.
     *
     * @var PerimeterCollection
     */
    protected PerimeterCollection $perimeters;

    /**
     * Add a route to the underlying route collection.
     *
     * @param Perimeter $perimeter
     * @return Perimeter
     */
    public function addPerimeter(Perimeter $perimeter): Perimeter
    {
        return $this->perimeters->add($perimeter);
    }
}