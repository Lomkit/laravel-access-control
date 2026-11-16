<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Module;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Memo;

class MemoControl extends Control
{
    protected string $model = Memo::class;
}
