<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class TicketControl extends Control
{
    protected string $model = Ticket::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} every ticket"))
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client tickets"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (Ticket $ticket): mixed => $ticket->getAttribute('client_id'),
                    reader: fn (User $user): mixed => $user->client_id,
                ),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own tickets"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                ->searchKeys(
                    name: 'author',
                    row: fn (Ticket $ticket): mixed => $ticket->getAttribute('author_id'),
                    reader: fn (User $user): mixed => $user->getKey(),
                ),
        ];
    }
}
