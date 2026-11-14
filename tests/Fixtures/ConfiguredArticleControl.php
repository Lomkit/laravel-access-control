<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;

class ConfiguredArticleControl extends ArticleControl
{
    use ConfiguresPerimeters;
}
