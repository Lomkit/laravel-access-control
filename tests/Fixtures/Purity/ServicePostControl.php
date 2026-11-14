<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class ServicePostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $rules = new PostRules;

        return [
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query, User $user) => $rules->authoredBy($query, $user)),
        ];
    }
}
