<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Clean\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\TicketReply;

class TicketReplyControl extends Control
{
    protected string $model = TicketReply::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('ticket', inverse: 'replies')->delegates(['view', 'update']),
        ];
    }
}
