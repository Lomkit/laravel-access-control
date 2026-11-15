<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Controls;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;

class ReplyControl extends Control
{
    protected string $model = Reply::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('thread', inverse: 'replies'),
            AuthorPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own replies"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                ->searchKeys(
                    name: 'author',
                    row: fn (Reply $reply): mixed => $reply->getAttribute('author_id'),
                    reader: fn (User $user): mixed => $user->getKey(),
                ),
        ];
    }
}
