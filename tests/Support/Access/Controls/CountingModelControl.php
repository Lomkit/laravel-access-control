<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

class CountingModelControl extends ModelControl
{
    /**
     * The number of times the perimeters were built.
     */
    public int $perimetersBuilt = 0;

    /**
     * Retrieve the list of perimeter definitions, counting each build.
     *
     * @return array<\Lomkit\Access\Perimeters\Perimeter>
     */
    protected function perimeters(): array
    {
        $this->perimetersBuilt++;

        return parent::perimeters();
    }
}
