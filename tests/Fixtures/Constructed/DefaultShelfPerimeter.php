<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

use Lomkit\Access\Perimeters\Perimeter;

final class DefaultShelfPerimeter extends Perimeter
{
    public function __construct(public readonly Shelf $shelf = Shelf::Featured) {}
}
