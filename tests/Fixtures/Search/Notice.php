<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

#[Unguarded]
#[Table('pages')]
#[ControlledBy(NoticeControl::class)]
class Notice extends Model
{
    use ControlledSearchable;
    use HasControl;

    /**
     * @param  Collection<array-key, static>  $models
     * @return Collection<array-key, static>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $this->withAccessKeys($models->filter(fn (Model $model): bool => $model->getAttribute('title') !== 'Hidden'));
    }

    /** @return BelongsToMany<Article, $this, ArticleNotice> */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_notice')->using(ArticleNotice::class);
    }

    /** @return array{id: mixed, title: mixed} */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->getAttribute('title'),
        ];
    }
}
