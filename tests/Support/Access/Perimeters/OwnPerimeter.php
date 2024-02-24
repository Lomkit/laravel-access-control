<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class OwnPerimeter extends Perimeter
{
    public string $name = 'own';

    public int $priority = 4;
}