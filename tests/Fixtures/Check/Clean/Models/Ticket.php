<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Clean\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

class Ticket extends Model
{
    use HasControl;

    /** @return HasMany<TicketReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }
}
