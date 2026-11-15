<?php

namespace Lomkit\Access\Tests\Fixtures\Threads;

use Lomkit\Access\Tests\Fixtures\ConfiguresPerimeters;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ThreadControl;

class ConfiguredThreadControl extends ThreadControl
{
    use ConfiguresPerimeters;
}
