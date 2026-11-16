<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Office;

class OfficeControl extends Control
{
    protected string $model = Office::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('country', inverse: 'offices')->delegates(['view', 'create' => 'view']),
        ];
    }
}
