<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Broken\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Broken\Kiosk;

class KioskControl extends Control
{
    protected string $model = Kiosk::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('country', inverse: 'kiosks'),
        ];
    }
}
