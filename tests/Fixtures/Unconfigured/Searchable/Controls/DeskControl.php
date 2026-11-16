<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Searchable\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Searchable\Desk;

class DeskControl extends Control
{
    protected string $model = Desk::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('country', inverse: 'desks'),
        ];
    }
}
