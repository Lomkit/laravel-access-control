<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Auth\Access\Response;

class DenyingUserPolicy
{
    public function update(User $user, User $model): Response
    {
        return Response::deny('Users are read-only.');
    }
}
