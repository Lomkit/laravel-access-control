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
        $signedIn = Auth::user();

        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query) use ($signedIn): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->where('client_id', $signedIn instanceof User ? $signedIn->client_id : null));
                }),
        ];
    }
}
