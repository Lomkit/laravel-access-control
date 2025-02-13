<?php

namespace Lomkit\Access\Tests\Support\Access\Perimeters;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;

class GlobalPerimeter extends Perimeter
{
    public function applies(Model $user): bool
    {
        return $user->should_global;
    }
}
