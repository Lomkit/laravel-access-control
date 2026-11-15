<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Controls;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;

class ThreadControl extends Control
{
    protected string $model = Thread::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global threads"))
                    ->unrestricted(),
                AuthorPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own threads"))
                    ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                    ->searchKeys(
                        name: 'author',
                        row: fn (Thread $thread): mixed => $thread->getAttribute('author_id'),
                        reader: fn (User $user): mixed => $user->getKey(),
                    ),
            ),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client threads"))
                ->query(fn (Builder $query, User $user): Builder => $query->whereRelation('forum', 'client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (Thread $thread): mixed => $thread->forum?->getAttribute('client_id'),
                    reader: fn (User $user): mixed => $user->client_id,
                    load: ['forum:id,client_id'],
                )
                ->reindexWhen(Forum::class, changed: ['client_id'], rows: fn (Forum $forum) => $forum->threads()),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id);
    }
}
