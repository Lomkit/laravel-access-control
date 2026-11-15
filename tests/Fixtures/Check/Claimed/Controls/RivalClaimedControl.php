<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Claimed\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Claimed\Models\Claimed;

class RivalClaimedControl extends Control
{
    protected string $model = Claimed::class;
}
