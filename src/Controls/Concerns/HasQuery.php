<?php

namespace Lomkit\Access\Controls\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Access\Exceptions\QueryNotImplemented;
use Lomkit\Access\Perimeters\Perimeter;

trait HasQuery
{
    public function runQuery(Builder $query)
    {
        if (($concernedPerimeters = $this->getConcernedPerimeters())->isNotEmpty()) {
            return tap($query, function (Builder $query) use ($concernedPerimeters) {
                foreach ($concernedPerimeters as $concernedPerimeter) {
                    $this->query($concernedPerimeter, $query);
                    if ($concernedPerimeter->final()) {
                        return;
                    }
                }
            });

            return;
        }

        return $this->fallbackQuery($query);
    }

    public function query(Perimeter $perimeter, Builder $query): Builder
    {
        $queryMethod = Str::camel($perimeter->name).'Query';

        if (method_exists($this, $queryMethod)) {
            $this->$queryMethod($query);

            return $query;
        }

        throw new QueryNotImplemented(sprintf('The %s method is not implemented in the %s class', $queryMethod, get_class($this)));
    }

    public function fallbackQuery(Builder $query): Builder
    {
        return $query;
    }
}
