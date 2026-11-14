<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Table;
use Lomkit\Access\Attributes\ControlledBy;

#[Table('articles')]
#[ControlledBy(QuietArticleControl::class)]
class QuietArticle extends Article
{
    #[\Override]
    public function searchIndexShouldBeUpdated(): bool
    {
        return false;
    }

    #[\Override]
    public function shouldBeSearchable(): bool
    {
        return $this->getAttribute('title') !== 'Hidden';
    }

    #[\Override]
    public function wasSearchableBeforeUpdate(): bool
    {
        return $this->getOriginal('title') !== 'Hidden';
    }
}
