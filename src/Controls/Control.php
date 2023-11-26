<?php

namespace Lomkit\Access\Controls;

use Lomkit\Access\Controls\Concerns\HasQuery;
use Lomkit\Access\Perimeters\Perimeters;

class Control
{
    use HasQuery;

    protected Perimeters $perimeters;

    public function __construct(Perimeters $perimeters)
    {
        $this->perimeters = $perimeters;
    }

    public function should(string $name) {
        $perimeter = $this->perimeters->findPerimeter($name);
    }
}