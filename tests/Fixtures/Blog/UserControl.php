<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;

class UserControl extends Control
{
    protected string $model = User::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client users"))
                ->should(fn (User $user, User $model): bool => $model->client_id === $user->client_id)
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id)),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own users"))
                ->should(fn (User $user, User $model): bool => $model->is($user))
                ->query(fn (Builder $query, User $user): Builder => $query->whereKey($user->getKey())),
        ];
    }
}
