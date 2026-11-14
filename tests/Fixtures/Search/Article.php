<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[UseFactory(ArticleFactory::class)]
class Article extends Model
{
    use HasControl;
    use HasFactory;
    use Searchable;

    /** @return array{id: int, author_id: ?int, title: string} */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'author_id' => $this->author_id,
            'title' => $this->title,
        ];
    }
}
