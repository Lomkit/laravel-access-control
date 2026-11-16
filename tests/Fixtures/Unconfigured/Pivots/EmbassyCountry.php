<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Access\Search\ReindexesSearch;

class EmbassyCountry extends Pivot
{
    use ReindexesSearch;

    protected $connection = 'unconfigured';
}
