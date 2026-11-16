<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Invalid;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Office;

class OrphanOfficeControl extends Control
{
    protected string $model = Office::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('region'),
        ];
    }
}
