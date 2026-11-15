<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance\Projects;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class ProjectControl extends Control
{
    protected string $model = Project::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global projects"))
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client projects"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id)),
        ];
    }
}
