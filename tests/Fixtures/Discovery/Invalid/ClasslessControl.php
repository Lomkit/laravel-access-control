<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Invalid;

use Lomkit\Access\Controls\Control;

class ClasslessControl extends Control
{
    protected string $model = 'Lomkit\Access\Tests\Fixtures\Discovery\Models\Missing';
}
