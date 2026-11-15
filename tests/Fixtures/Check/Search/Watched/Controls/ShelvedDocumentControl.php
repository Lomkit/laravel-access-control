<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Watched\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Search\Watched\Models\Shelf;
use Lomkit\Access\Tests\Fixtures\Check\Search\Watched\Models\ShelvedDocument;

class ShelvedDocumentControl extends Control
{
    protected string $model = ShelvedDocument::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(function (Builder $query, Model $user): void {
                    $query->whereRelation('shelf', 'client_id', $user->getAttribute('client_id'));
                })
                ->searchKeys(
                    name: 'client',
                    row: fn (ShelvedDocument $document): mixed => $document->shelf?->getAttribute('client_id'),
                    reader: fn (Model $user): mixed => $user->getAttribute('client_id'),
                    load: ['shelf:id,client_id'],
                )
                ->reindexWhen(Shelf::class, changed: ['client_id'], rows: fn (Shelf $shelf): Builder => ShelvedDocument::query()->where('shelf_id', $shelf->getKey())),
        ];
    }
}
