<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;

class NoticeControl extends Control
{
    protected string $model = Notice::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            AuthorPerimeter::new()
                ->allowed(fn (Model $user): bool => true)
                ->query(function (Builder $query): void {
                    $query->where('title', '!=', 'Draft');
                })
                ->searchKeys('titled', fn (Notice $notice): mixed => $notice->getAttribute('title'), fn (Model $user): array => []),
        ];
    }
}
