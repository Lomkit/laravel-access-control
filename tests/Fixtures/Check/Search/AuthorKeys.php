<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\AdminPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;

class AuthorKeys
{
    /** @return list<Perimeter> */
    public static function perimeters(): array
    {
        return [
            AdminPerimeter::new()
                ->allowed(fn (Model $user): bool => $user->getAttribute('admin') === true)
                ->unrestricted(),
            OwnPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(function (Builder $query, Model $user): void {
                    $query->where('author_id', $user->getKey());
                })
                ->searchKeys(
                    name: 'author',
                    row: fn (Model $row): mixed => $row->getAttribute('author_id'),
                    reader: fn (Model $user): mixed => $user->getKey(),
                ),
        ];
    }
}
