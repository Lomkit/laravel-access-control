<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Support\Collection;
use Lomkit\Access\Attributes\ControlledBy;

#[Table('articles')]
#[ControlledBy(KeylessArticleControl::class)]
class KeylessArticle extends Article
{
    /**
     * @param  Collection<array-key, static>  $models
     * @return Collection<array-key, static>
     */
    #[\Override]
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models;
    }
}
