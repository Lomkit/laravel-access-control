<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Drafts\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Models\LedgerLine;

class LedgerLineControl extends Control
{
    protected string $model = LedgerLine::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('ledger', inverse: 'lines')->delegates(['view', 'update']),
        ];
    }
}
