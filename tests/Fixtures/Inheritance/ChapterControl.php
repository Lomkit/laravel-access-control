<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance;

use Lomkit\Access\Controls\Control;

class ChapterControl extends Control
{
    protected string $model = Chapter::class;
}
