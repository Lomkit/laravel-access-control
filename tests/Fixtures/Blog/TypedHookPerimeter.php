<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\Perimeter;

class TypedHookPerimeter extends Perimeter
{
    #[\Override]
    public function applyAllowedCallback(Model $user, Ability $ability): bool
    {
        return $ability->is('view');
    }
}
