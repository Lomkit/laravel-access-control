<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Lomkit\Access\Perimeters\Perimeter;

class SitePerimeter extends Perimeter
{
    protected string $name = 'site';

    protected int $priority = 2;
}