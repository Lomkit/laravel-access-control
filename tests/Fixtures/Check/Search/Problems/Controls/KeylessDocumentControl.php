<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\KeylessDocument;

class KeylessDocumentControl extends Control
{
    protected string $model = KeylessDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            OwnPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(function (Builder $query, Model $user): void {
                    $query->where('author_id', $user->getKey());
                }),
        ];
    }
}
