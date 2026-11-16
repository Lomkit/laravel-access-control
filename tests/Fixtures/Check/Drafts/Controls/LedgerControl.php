<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Drafts\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Check\Drafts\Models\Ledger;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\TeamPerimeter;

class LedgerControl extends Control
{
    protected string $model = Ledger::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            Levels::of(
                TeamPerimeter::new()
                    ->allowed(fn (Model $user): bool => true)
                    ->query(function (Builder $query, Model $user): void {
                        $query->where('team_id', $user->getAttribute('team_id'));
                    }),
                OwnPerimeter::new()
                    ->allowed(fn (Model $user): bool => true)
                    ->query(function (Builder $query, Model $user): void {
                        $query->where('author_id', $user->getKey());
                    }),
            ),
        ];
    }
}
