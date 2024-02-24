<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class ClientPerimeter extends Perimeter
{
    public string $name = 'client';

    public int $priority = 2;
}
