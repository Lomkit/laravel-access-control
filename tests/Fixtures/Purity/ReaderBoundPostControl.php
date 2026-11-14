<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class ReaderBoundPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $user = Auth::user();
        $client = $user instanceof User ? $user->client_id : null;

        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->should(fn (User $user, $post): bool => $post->project->client_id === $client)
                ->query(function (Builder $query) use ($client): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->where('client_id', $client));
                }),
        ];
    }
}
