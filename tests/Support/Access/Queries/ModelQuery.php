<?php

namespace Lomkit\Access\Tests\Support\Access\Queries;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Queries\Query;

class ModelQuery extends Query
{
    public function clientQuery(Builder $query) {
        dd($query);
    }
}