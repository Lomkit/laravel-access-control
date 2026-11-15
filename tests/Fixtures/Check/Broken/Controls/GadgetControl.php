<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Broken\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Check\Broken\Models\Gadget;

class GadgetControl extends Control
{
    protected string $model = Gadget::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [Levels::of()];
    }
}
