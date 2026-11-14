<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Controls\Control;

class UserControl extends Control
{
    protected string $model = User::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user, string $method): bool => $user->can("{$method} client users"))
                ->should(fn (User $user, User $model): bool => $model->client_id === $user->client_id)
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id)),
            OwnPerimeter::new()
                ->allowed(fn (User $user, string $method): bool => $user->can("{$method} own users"))
                ->should(fn (User $user, User $model): bool => $model->is($user))
                ->query(fn (Builder $query, User $user): Builder => $query->whereKey($user->getKey())),
        ];
    }
}
