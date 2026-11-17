<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Gated;

use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketReplyControl;

class RowContextTicketReplyControl extends TicketReplyControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('ticket', inverse: 'replies')->decidedInRowContext('brand_id'),
        ];
    }
}
