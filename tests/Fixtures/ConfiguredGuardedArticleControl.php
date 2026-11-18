<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Tests\Fixtures\SearchGuard\GuardedArticleControl;

class ConfiguredGuardedArticleControl extends GuardedArticleControl
{
    use ConfiguresPerimeters;
}
