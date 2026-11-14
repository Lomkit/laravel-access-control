<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Search\SearchKeys;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class FolderClientBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->whereRelation('folder', 'client_id', $reader instanceof User ? $reader->client_id : 0);
    }

    #[\Override]
    protected function searchKeys(): SearchKeys
    {
        return SearchKeys::of(
            name: 'folder_client',
            row: fn (Article $article): mixed => $article->folder?->client_id,
            reader: fn (User $user): mixed => $user->client_id,
            load: ['folder:id,client_id'],
        );
    }
}
