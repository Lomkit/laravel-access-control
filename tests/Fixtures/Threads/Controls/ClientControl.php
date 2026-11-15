<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Controls;

use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;

class ClientControl extends Control
{
    protected string $model = Client::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global clients"))
                ->unrestricted(),
        ];
    }
}
