<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Rival;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\Post;

class RivalPostControl extends Control
{
    protected string $model = Post::class;
}
