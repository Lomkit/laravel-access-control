<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Controls\Control;

class PostControl extends Control
{
    protected string $model = Post::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, string $method): bool => $user->can("{$method} global posts"))
                ->should(fn (User $user, Post $post): bool => true)
                ->query(fn (Builder $query, User $user): Builder => $query),
            ClientPerimeter::new()
                ->allowed(fn (User $user, string $method): bool => $user->can("{$method} client posts"))
                ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client))
                ->query(fn (Builder $query, User $user): Builder => $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client))),
            OwnPerimeter::new()
                ->allowed(fn (User $user, string $method): bool => $user->can("{$method} own posts"))
                ->should(fn (User $user, Post $post): bool => $post->author()->is($user))
                ->query(fn (Builder $query, User $user): Builder => $query->whereBelongsTo($user, 'author')),
        ];
    }
}
