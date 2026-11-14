<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\Perimeter;

class MixedHookPerimeter extends Perimeter
{
    #[\Override]
    public function applyAllowedCallback(Model $user, mixed $ability): bool
    {
        return $ability instanceof Ability && $ability->is('view');
    }
}
