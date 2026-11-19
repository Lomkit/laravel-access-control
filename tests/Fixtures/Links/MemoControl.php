<?php

namespace Lomkit\Access\Tests\Fixtures\Links;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class MemoControl extends Control
{
    protected string $model = Memo::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            LinkedSubjectsPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view') && $user->can('view memos'))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('links', fn (Builder $links) => $links->whereHasMorph(
                        'subject',
                        [Post::class, User::class],
                        fn (Builder $subjects) => $subjects->controlledFor($user),
                    ));
                }),
        ];
    }
}
