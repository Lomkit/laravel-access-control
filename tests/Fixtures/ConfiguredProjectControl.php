<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Tests\Fixtures\Inheritance\Projects\ProjectControl;

class ConfiguredProjectControl extends ProjectControl
{
    use ConfiguresPerimeters;
}
