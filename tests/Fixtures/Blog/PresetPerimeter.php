<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Closure;
use Lomkit\Access\Perimeters\Perimeter;

class PresetPerimeter extends Perimeter
{
    protected ?Closure $allowedCallback = null;

    protected ?Closure $queryCallback = null;

    public static function opening(): static
    {
        $perimeter = static::new();
        $perimeter->allowedCallback = fn (): bool => true;
        $perimeter->queryCallback = fn ($query) => $query->whereRaw('1=1');

        return $perimeter;
    }
}
