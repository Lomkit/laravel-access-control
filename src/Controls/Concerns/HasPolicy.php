<?php

namespace Lomkit\Access\Controls\Concerns;

use http\Exception\RuntimeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lomkit\Access\Exceptions\QueryNotImplemented;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Queries\Query;

trait HasPolicy
{
    public function runPolicy(Model $model) {
        if (($concernedPerimeters = $this->getConcernedPerimeters())->isNotEmpty()) {
            return $this->policy($concernedPerimeters->first(), $model);
        }

        return false;
    }

    public function policy(Perimeter $perimeter, Model $model) : Builder {
        // @TODO: here verify the policy is ok :) + for the "shared" example, implement the fact that for the query you can add multiple query
//        $queryMethod = Str::camel($perimeter->name).'Query';
//
//        if (method_exists($this, $queryMethod)) {
//            $this->$queryMethod($query);
//            return $query;
//        }
//
//        throw new QueryNotImplemented(sprintf('The %s method is not implemented in the %s class', $queryMethod, get_class($this)));
    }
//
//    public function fallbackQuery(Builder $query) : Builder {
//        return $query;
//    }
}