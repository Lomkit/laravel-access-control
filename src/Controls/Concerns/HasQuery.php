<?php

namespace Lomkit\Access\Controls\Concerns;

use http\Exception\RuntimeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lomkit\Access\Exceptions\QueryNotImplemented;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Queries\Query;

trait HasQuery
{
    public function runQuery(Builder $query) {
        if (($concernedPerimeters = $this->getConcernedPerimeters())->isNotEmpty()) {
            return $this->query($concernedPerimeters->first(), $query);
        }

        return $this->fallbackQuery($query);
    }

    public function query(Perimeter $perimeter, Builder $query) : Builder {
        $queryMethod = Str::camel($perimeter->name).'Query';

        if (method_exists($this, $queryMethod)) {
            $this->$queryMethod($query);
            return $query;
        }

        throw new QueryNotImplemented(sprintf('The %s method is not implemented in the %s class', $queryMethod, get_class($this)));
    }

    public function fallbackQuery(Builder $query) : Builder {
        return $query;
    }
}