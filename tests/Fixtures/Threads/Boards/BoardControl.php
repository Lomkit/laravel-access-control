<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Boards;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\Board;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;

class BoardControl extends Control
{
    protected string $model = Board::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client boards"))
                ->query(fn (Builder $query, User $user): Builder => $query->whereRelation('forum', 'client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (Board $board): mixed => $board->forum?->getAttribute('client_id'),
                    reader: fn (User $user): mixed => $user->client_id,
                    load: ['forum:id,client_id'],
                )
                ->reindexWhen(Forum::class, changed: ['client_id'], rows: fn (Forum $forum) => $forum->boards()),
        ];
    }
}
