<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

interface SearchScope
{
    /** @param  Builder<covariant Model>  $search */
    public function prepare(Builder $search, mixed $engine): void;
}
