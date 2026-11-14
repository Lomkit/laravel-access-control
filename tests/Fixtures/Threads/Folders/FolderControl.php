<?php

namespace Lomkit\Access\Tests\Fixtures\Threads\Folders;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Folder;

class FolderControl extends Control
{
    protected string $model = Folder::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client folders"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id))
                ->searchKeys(
                    name: 'client',
                    row: fn (Folder $folder): mixed => $folder->getAttribute('client_id'),
                    reader: fn (User $user): mixed => $user->client_id,
                ),
        ];
    }
}
