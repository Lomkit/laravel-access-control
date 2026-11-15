<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Policies;

use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketReplyControl;

class TicketPolicyNamingReplies extends ControlledPolicy
{
    protected string $control = TicketReplyControl::class;
}
