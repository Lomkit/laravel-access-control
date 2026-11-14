<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

class EditorialPerimeter extends Perimeter
{
    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        $reader = $context->reader();

        return $reader instanceof User && $reader->can("{$context->ability()} editorial posts");
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        $reader = $context->reader();

        if (! $reader instanceof User || ! $row instanceof Post) {
            return false;
        }

        if ($context->ability()->is('view')) {
            return $row->project->client()->is($reader->client);
        }

        return $row->author()->is($reader);
    }

    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        if (! $reader instanceof User) {
            $query->whereRaw('0=1');

            return;
        }

        $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($reader->client));
    }
}
