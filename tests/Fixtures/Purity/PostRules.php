<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class PostRules
{
    public int $calls = 0;

    public function authoredBy(Builder $query, User $user): void
    {
        $this->calls++;

        $query->whereBelongsTo($user, 'author');
    }
}
