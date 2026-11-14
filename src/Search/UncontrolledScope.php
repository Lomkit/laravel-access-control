<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

final class UncontrolledScope implements SearchScope
{
    /** @param  Builder<covariant Model>  $search */
    #[\Override]
    public function prepare(Builder $search, mixed $engine): mixed
    {
        return $engine;
    }
}
