<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Snippet;

class SnippetControl extends Control
{
    protected string $model = Snippet::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (Model $user, Ability $ability): bool => $user->can("{$ability} global snippets"))
                ->unrestricted(),
        ];
    }
}
