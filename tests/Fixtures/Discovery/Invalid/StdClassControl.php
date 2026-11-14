<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Invalid;

use Lomkit\Access\Controls\Control;
use stdClass;

class StdClassControl extends Control
{
    protected string $model = stdClass::class;
}
