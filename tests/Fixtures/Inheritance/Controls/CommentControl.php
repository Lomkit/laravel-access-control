<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance\Controls;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class CommentControl extends Control
{
    protected string $model = Comment::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ThroughParent::belongsTo('post', inverse: 'comments'),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own comments"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey())),
        ];
    }
}
