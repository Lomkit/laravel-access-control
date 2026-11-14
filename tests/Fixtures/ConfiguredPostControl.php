<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Tests\Fixtures\Blog\PostControl;

class ConfiguredPostControl extends PostControl
{
    use ConfiguresPerimeters;
}
