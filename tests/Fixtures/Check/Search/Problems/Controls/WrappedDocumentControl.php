<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\WrappedDocument;
use Lomkit\Access\Tests\Fixtures\Purity\WrappingPerimeter;

class WrappedDocumentControl extends Control
{
    protected string $model = WrappedDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            WrappingPerimeter::new()->wrapping(
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
            ),
        ];
    }
}
