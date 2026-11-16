<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

enum Shelf
{
    case Featured;
    case Plain;

    public function holdsFeatured(): bool
    {
        return $this === self::Featured;
    }
}
