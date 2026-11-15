<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Policies;

use Illuminate\Database\Eloquent\Model;

class HandWrittenTicketPolicy
{
    public function view(Model $user, Model $ticket): bool
    {
        return $ticket->getAttribute('author_id') === $user->getKey();
    }
}
