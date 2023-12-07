<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class OwnPerimeter extends Perimeter
{
    protected string $name = 'own';

    protected int $priority = 3;
}