<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[Table('tickets')]
#[ControlledBy(MislabeledTicketControl::class)]
class MislabeledTicket extends Model
{
    use HasControl;
}
