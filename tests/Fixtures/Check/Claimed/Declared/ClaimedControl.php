<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Claimed\Declared;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Claimed\Models\Claimed;

class ClaimedControl extends Control
{
    protected string $model = Claimed::class;
}
