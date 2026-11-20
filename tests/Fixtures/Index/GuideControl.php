<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class GuideControl extends Control
{
    protected string $model = Guide::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            Levels::of(
                GlobalPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} every guide"))
                    ->unrestricted(),
                OwnPerimeter::new()
                    ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own guides"))
                    ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                    ->searchKeys(
                        name: 'author',
                        row: fn (Guide $guide): mixed => $guide->getAttribute('author_id'),
                        reader: fn (User $user): mixed => $user->getKey(),
                    ),
            ),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client guides"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (Guide $guide): mixed => $guide->getAttribute('client_id'),
                    reader: fn (User $user): mixed => $user->client_id,
                ),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return new GuideBrandBoundary;
    }
}
