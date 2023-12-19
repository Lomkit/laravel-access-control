<?php

namespace Lomkit\Access\Tests\Support\Access\Queries;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Queries\Query;

class ModelQuery extends Query
{
    public function clientQuery(Builder $query) {
        $query->where('is_client', true);
    }

    public function siteQuery(Builder $query) {
        $query->where('is_site', true);
    }

    public function ownQuery(Builder $query) {
        $query->where('is_own', true);
    }
}