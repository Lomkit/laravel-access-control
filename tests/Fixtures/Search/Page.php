<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
class Page extends Model
{
    use HasControl;
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
