<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Access\Search\ReindexesSearch;

class DocumentTag extends Pivot
{
    use ReindexesSearch;
}
