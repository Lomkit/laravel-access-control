<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class SitePerimeter extends Perimeter
{
    public string $name = 'site';

    public int $priority = 2;
}