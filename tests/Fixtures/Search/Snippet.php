<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[Table('pages')]
class Snippet extends Model
{
    use HasControl;
    use Searchable;
}
