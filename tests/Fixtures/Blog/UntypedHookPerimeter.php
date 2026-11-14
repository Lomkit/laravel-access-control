<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;

class UntypedHookPerimeter extends Perimeter
{
    #[\Override]
    public function applyAllowedCallback(Model $user, $ability): bool
    {
        return match ($ability) {
            'delete' => false,
            default => true,
        };
    }
}
