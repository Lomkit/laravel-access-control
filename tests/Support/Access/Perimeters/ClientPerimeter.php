<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class ClientPerimeter extends Perimeter
{
    protected string $name = 'client';

    protected int $priority = 1;
}