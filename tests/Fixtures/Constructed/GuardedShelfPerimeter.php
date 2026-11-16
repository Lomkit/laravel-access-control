<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

use Lomkit\Access\Perimeters\Perimeter;

final class GuardedShelfPerimeter extends Perimeter
{
    protected function __construct() {}

    public static function guarded(): self
    {
        return new self;
    }
}
