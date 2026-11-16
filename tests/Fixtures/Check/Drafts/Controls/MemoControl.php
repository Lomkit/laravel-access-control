<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Drafts\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Models\Memo;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\SqlWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;

class MemoControl extends Control
{
    protected string $model = Memo::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            OwnPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->should(fn (Model $user, Memo $memo): bool => $memo->getAttribute('author_id') === $user->getKey())
                ->query(function (Builder $query, Model $user): void {
                    $query->where('author_id', $user->getKey());
                }),
        ];
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return new SqlWorkspaceBoundary;
    }
}
