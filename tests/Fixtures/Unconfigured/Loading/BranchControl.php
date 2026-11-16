<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Loading;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;

class BranchControl extends Control
{
    protected string $model = Branch::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            ClientPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(fn (Builder $query): Builder => $query)
                ->searchKeys(
                    name: 'country',
                    row: fn (Branch $branch): mixed => $branch->country?->getAttribute('code'),
                    reader: fn (Model $user): mixed => $user->getAttribute('country_code'),
                    load: ['country:id,code'],
                ),
            SharedPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(fn (Builder $query): Builder => $query)
                ->searchKeys(
                    name: 'ministry',
                    row: fn (Branch $branch): mixed => $branch->getAttribute('ministry_id'),
                    reader: fn (Model $user): mixed => $user->getAttribute('ministry_id'),
                    load: ['ministry'],
                ),
        ];
    }
}
