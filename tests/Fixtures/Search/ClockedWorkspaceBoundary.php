<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Search\SearchKeys;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class ClockedWorkspaceBoundary extends WorkspaceSearchBoundary
{
    #[\Override]
    protected function searchKeys(): SearchKeys
    {
        return SearchKeys::of(
            name: 'workspace',
            row: fn (Model $row): array => [[now()->year, $row->getAttribute('workspace_id')]],
            reader: fn (User $user): array => [[now()->year, $user->current_workspace_id]],
        );
    }
}
