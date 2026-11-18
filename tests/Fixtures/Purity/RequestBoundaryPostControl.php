<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class RequestBoundaryPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return new TitleBoundary(request()->query('title'));
    }
}
