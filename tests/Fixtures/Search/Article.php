<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[UseFactory(ArticleFactory::class)]
#[ControlledBy(ArticleControl::class)]
class Article extends Model
{
    use HasControl;
    use HasFactory;
    use Searchable;

    #[\Override]
    protected function casts(): array
    {
        return ['featured' => 'boolean'];
    }

    /** @return array{id: int, author_id: ?int, title: string, featured: bool} */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'author_id' => $this->author_id,
            'title' => $this->title,
            'featured' => $this->featured,
        ];
    }
}
