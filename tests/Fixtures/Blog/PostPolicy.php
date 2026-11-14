<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Lomkit\Access\Access;
use Lomkit\Access\Policies\ControlledPolicy;

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;

    public function approve(User $user, Post $post): bool
    {
        $control = Access::controlFor($post);

        return $control->applies($user, $control->abilityFor(__FUNCTION__), $post);
    }
}
