<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Controls;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;

class ForumControl extends Control
{
    protected string $model = Forum::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global forums"))
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client forums"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (Forum $forum): mixed => $forum->getAttribute('client_id'),
                    reader: fn (User $user): mixed => $user->client_id,
                ),
        ];
    }
}
