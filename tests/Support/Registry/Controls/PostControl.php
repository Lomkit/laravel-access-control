<?php

namespace Lomkit\Access\Tests\Support\Registry\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Registry\Models\Post;

class PostControl extends Control
{
    protected string $model = Post::class;
}
