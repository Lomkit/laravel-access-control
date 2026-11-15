<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Access\Controls\HasControl;

class PlainDocument extends Model
{
    use HasControl;
    use Searchable;
}
