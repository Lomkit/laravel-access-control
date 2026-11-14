<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;

class PostControl extends Control
{
    protected string $model = Post::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            SharedPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} shared posts"))
                ->should(fn (User $user, Post $post): bool => $post->sharedWith()->whereKey($user->getKey())->exists())
                ->query(fn (Builder $query, User $user): Builder => $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()))),
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global posts"))
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client posts"))
                ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own posts"))
                ->should(fn (User $user, Post $post): bool => $post->author()->is($user))
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                }),
        ];
    }
}
