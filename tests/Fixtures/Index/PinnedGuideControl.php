<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class PinnedGuideControl extends Control
{
    protected string $model = PinnedGuide::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} every guide"))
                ->unrestricted(),
        ];
    }
}
