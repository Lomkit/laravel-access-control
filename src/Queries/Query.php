<?php

namespace Lomkit\Access\Queries;

use http\Exception\RuntimeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lomkit\Access\Perimeters\Perimeter;

class Query
{
    public function query(Perimeter $perimeter, Builder $query) : Builder {
        $queryMethod = Str::camel($perimeter->name).'Query';

        if (method_exists($this, $queryMethod)) {
            $this->$queryMethod($query);
            return $query;
        }

        throw new RuntimeException(sprintf('The %s method is not implemented in the %s class', $queryMethod, get_class($this)));
    }

    public function defaultQuery(Builder $query) : Builder {
        return $query;
    }
}