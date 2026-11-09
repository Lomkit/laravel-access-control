<?php

namespace Lomkit\Access\Tests\Support\Registry\Controls\Admin;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Registry\Models\Admin\Post;

class PostControl extends Control
{
    protected string $model = Post::class;
}
