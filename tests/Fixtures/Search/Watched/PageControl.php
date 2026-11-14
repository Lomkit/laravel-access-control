<?php

namespace Lomkit\Access\Tests\Fixtures\Search\Watched;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\Folder;
use Lomkit\Access\Tests\Fixtures\Search\FolderPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Page;

class PageControl extends Control
{
    public static int $declarations = 0;

    protected string $model = Page::class;

    #[\Override]
    protected function perimeters(): array
    {
        self::$declarations++;

        return [
            FolderPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereRelation('folder', 'client_id', $user->client_id);
                })
                ->searchKeys(
                    name: 'client',
                    row: fn (Page $page): mixed => $page->folder?->client_id,
                    reader: fn (User $user): mixed => $user->client_id,
                    load: ['folder:id,client_id'],
                )
                ->reindexWhen(Folder::class, changed: ['client_id'], rows: fn (Folder $folder) => $folder->pages()),
        ];
    }
}
