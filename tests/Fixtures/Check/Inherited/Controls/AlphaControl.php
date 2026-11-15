<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Inherited\Controls;

use Lomkit\Access\Tests\Fixtures\Check\Inherited\Models\Alpha;

class AlphaControl extends TeamControl
{
    protected string $model = Alpha::class;
}
