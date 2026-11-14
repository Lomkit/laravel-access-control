<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class SignedInPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        ];
    }
}
