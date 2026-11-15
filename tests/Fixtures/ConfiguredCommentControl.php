<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Tests\Fixtures\Inheritance\Controls\CommentControl;

class ConfiguredCommentControl extends CommentControl
{
    use ConfiguresPerimeters;
}
