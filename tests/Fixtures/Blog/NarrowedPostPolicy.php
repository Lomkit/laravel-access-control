<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class NarrowedPostPolicy extends PostPolicy
{
    #[\Override]
    public function update(Model $user, Model $model): Response
    {
        if ($model->getAttribute('title') === 'Roadmap') {
            return Response::deny('The roadmap is frozen.', 'frozen');
        }

        return parent::update($user, $model);
    }
}
