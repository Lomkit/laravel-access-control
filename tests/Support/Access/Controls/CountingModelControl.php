<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Lomkit\Access\Perimeters\Perimeter;

class CountingModelControl extends ModelControl
{
    /**
     * The number of times the perimeters were built.
     */
    public int $perimetersBuilt = 0;

    /**
     * Retrieve the list of perimeter definitions, counting each build.
     *
     * @return array<Perimeter>
     */
    protected function perimeters(): array
    {
        $this->perimetersBuilt++;

        return parent::perimeters();
    }
}
