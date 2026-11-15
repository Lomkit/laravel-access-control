<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Policies;

use LogicException;

class ThrowingTicketPolicy
{
    public function __construct()
    {
        throw new LogicException('The ticket policy needs a tenant.');
    }
}
