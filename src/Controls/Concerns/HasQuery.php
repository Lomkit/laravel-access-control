<?php

namespace Lomkit\Access\Controls\Concerns;

use Lomkit\Access\Queries\Query;

trait HasQuery
{
    public Query $query;
}