<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Routing\Route;

class PerimeterCollection
{
    /**
     * An array of the perimeters keyed by priority.
     *
     * @var array
     */
    protected array $perimeters = [];

    /**
     * A flattened array of all of the perimeters.
     *
     * @var Perimeter[]
     */
    protected array $allPerimeters = [];

    /**
     * Add a Perimeter instance to the collection.
     *
     * @param  Perimeter  $perimeter
     * @return Perimeter
     */
    public function add(Perimeter $perimeter): Perimeter
    {
        $this->addToCollections($perimeter);

        return $perimeter;
    }

    /**
     * Add the given perimeter to the arrays of perimeters.
     *
     * @param  Perimeter $perimeter
     * @return void
     */
    protected function addToCollections(Perimeter $perimeter)
    {
        $this->perimeters[$perimeter->priority][] = $perimeter;

        $this->allPerimeters[] = $perimeter;
    }
}