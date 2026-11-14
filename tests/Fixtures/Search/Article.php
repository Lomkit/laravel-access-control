<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

#[Unguarded]
#[UseFactory(ArticleFactory::class)]
class Article extends Model
{
    use HasFactory;
    use Searchable;

    /** @return array{id: int, title: string} */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
        ];
    }
}
