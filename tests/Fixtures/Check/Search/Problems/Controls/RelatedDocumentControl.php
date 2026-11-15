<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\RelatedDocument;

class RelatedDocumentControl extends Control
{
    protected string $model = RelatedDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(function (Builder $query, Model $user): void {
                    $query->whereRelation('folder', 'client_id', $user->getAttribute('client_id'));
                })
                ->searchKeys(
                    name: 'client',
                    row: fn (RelatedDocument $document): mixed => $document->folder?->getAttribute('client_id'),
                    reader: fn (Model $user): mixed => $user->getAttribute('client_id'),
                    load: ['folder:id,client_id'],
                ),
        ];
    }
}
