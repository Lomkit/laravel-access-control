<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\OtherModule;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Memo;

class RivalMemoControl extends Control
{
    protected string $model = Memo::class;
}
