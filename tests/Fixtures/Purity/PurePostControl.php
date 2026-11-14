<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class PurePostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $approve = Ability::of('approve');
        $visibility = Visibility::Public;
        $byAuthor = function (Builder $query, User $user): void {
            $query->whereBelongsTo($user, 'author');
        };

        return [
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view') || $ability->is($approve))
                ->query(fn (Builder $query, User $user) => $byAuthor($query, $user)),
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => $visibility === Visibility::Public)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
                }),
        ];
    }
}
