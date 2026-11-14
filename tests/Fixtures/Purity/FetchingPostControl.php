<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class FetchingPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $project = Project::query()->firstOrFail();

        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query) use ($project): void {
                    $query->whereBelongsTo($project);
                }),
        ];
    }
}
