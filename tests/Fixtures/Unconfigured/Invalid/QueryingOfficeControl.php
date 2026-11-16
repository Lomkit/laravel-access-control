<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Invalid;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Unconfigured\QueryingOffice;

class QueryingOfficeControl extends Control
{
    protected string $model = QueryingOffice::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('country'),
        ];
    }
}
