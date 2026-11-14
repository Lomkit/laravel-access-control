<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class JoiningWorkspaceBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->join('projects', 'projects.id', '=', 'posts.project_id')->where('posts.workspace_id', 1);
    }
}
