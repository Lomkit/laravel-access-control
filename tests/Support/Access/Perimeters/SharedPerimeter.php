<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class SharedPerimeter extends Perimeter
{
    public string $name = 'shared';

    public int $priority = 1;
}