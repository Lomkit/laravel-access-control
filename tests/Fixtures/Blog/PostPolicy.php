<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Auth\Access\Response;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Policies\ControlledPolicy;

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;

    public function approve(User $user, Post $post): Response
    {
        return $this->decide(__FUNCTION__, $user, $post);
    }

    public function export(User $user): Response
    {
        return $this->decide(__FUNCTION__, $user);
    }

    public function duplicate(User $user, Post $post, Post $copy): Response
    {
        return $this->decide(__FUNCTION__, $user, Draft::of($copy));
    }
}
