<?php

namespace Lomkit\Access\Controls\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Lomkit\Access\Queries\Query;

trait HasQuery
{
    /**
     * The query the control corresponds to.
     *
     * @var class-string<Query>
     */
    protected string $query;

    /**
     * Get a fresh instance of the query related to the control.
     *
     * @return Query
     */
    public function newQuery(): Query
    {
        $query = $this->query;

        return new $query();
    }

    public function runQuery(Builder $query) {
        $queryObject = $this->newQuery();

        foreach ($this->perimeters->getPerimeters() as $perimeter) {
            if ($this->should($perimeter)) {
                return $queryObject->query($perimeter, $query);
            }
        }

        return $queryObject->fallbackQuery($query);
    }
}