<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

#[Unguarded]
class Page extends Model
{
    use ControlledSearchable;
    use HasControl;

    /** @return array{id: mixed, title: mixed} */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->getAttribute('title'),
        ];
    }
}
