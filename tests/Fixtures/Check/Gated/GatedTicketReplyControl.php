<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Gated;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketReplyControl;

class GatedTicketReplyControl extends TicketReplyControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('ticket', inverse: 'replies')
                ->delegates(['view', 'update', 'create' => 'update'])
                ->allowed(fn (Model $user, Ability $ability): bool => $user->can("{$ability} replies")),
        ];
    }
}
