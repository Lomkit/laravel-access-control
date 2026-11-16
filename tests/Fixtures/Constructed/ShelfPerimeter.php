<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

use Lomkit\Access\Perimeters\Perimeter;

final class ShelfPerimeter extends Perimeter
{
    public function __construct(
        public readonly Shelf $shelf,
        public readonly bool $writing,
    ) {}

    public static function reading(Shelf $shelf): self
    {
        return new self($shelf, false);
    }

    public static function writing(Shelf $shelf): self
    {
        return new self($shelf, true);
    }
}
