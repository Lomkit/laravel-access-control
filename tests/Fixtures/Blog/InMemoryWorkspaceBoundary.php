<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

class InMemoryWorkspaceBoundary extends WorkspaceBoundary
{
    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        $reader = $context->reader();

        if (! $reader instanceof User || $row->getAttribute('workspace_id') !== $reader->current_workspace_id) {
            return false;
        }

        return $context->ability()->is('view') || $row->getAttribute('archived_at') === null;
    }
}
