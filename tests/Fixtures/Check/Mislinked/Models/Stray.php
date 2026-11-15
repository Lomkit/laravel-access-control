<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Mislinked\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketControl;

#[ControlledBy(TicketControl::class)]
class Stray extends Model
{
    use HasControl;
}
