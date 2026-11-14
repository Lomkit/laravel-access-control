<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Lomkit\Access\Policies\ControlledPolicy;

class PostPolicy extends ControlledPolicy
{
    protected string $control = PostControl::class;
}
