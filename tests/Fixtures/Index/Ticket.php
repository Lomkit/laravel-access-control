<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[ControlledBy(TicketControl::class)]
class Ticket extends Model
{
    use HasControl;
    use SoftDeletes;
}
