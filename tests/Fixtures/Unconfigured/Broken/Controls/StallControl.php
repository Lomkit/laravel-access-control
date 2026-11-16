<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Broken\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Broken\Stall;

class StallControl extends Control
{
    protected string $model = Stall::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('country', inverse: 'stalls'),
        ];
    }
}
