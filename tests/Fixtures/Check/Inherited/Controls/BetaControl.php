<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Inherited\Controls;

use Lomkit\Access\Tests\Fixtures\Check\Inherited\Models\Beta;

class BetaControl extends TeamControl
{
    protected string $model = Beta::class;
}
