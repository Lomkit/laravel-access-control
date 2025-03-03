<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\OverlayPerimeter;
use Lomkit\Access\Perimeters\Perimeter;

class SharedPerimeter extends OverlayPerimeter
{
    public function applies(Model $user): bool
    {
        return $user->should_shared;
    }
}
