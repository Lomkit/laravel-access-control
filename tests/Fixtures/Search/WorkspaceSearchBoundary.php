<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Search\SearchKeys;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class WorkspaceSearchBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->where('workspace_id', $reader instanceof User ? $reader->current_workspace_id : 0);
    }

    #[\Override]
    protected function searchKeys(): SearchKeys
    {
        return SearchKeys::of(
            name: 'workspace',
            row: fn (Model $row): mixed => $row->getAttribute('workspace_id'),
            reader: fn (User $user): mixed => $user->current_workspace_id,
        );
    }
}
