<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class MislabeledTicketControl extends Control
{
    protected string $model = MislabeledTicket::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client tickets"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (MislabeledTicket $ticket): mixed => $ticket->getAttribute('title'),
                    reader: fn (User $user): string => 'Slow export',
                ),
        ];
    }
}
