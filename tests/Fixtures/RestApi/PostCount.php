<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Metrics\Value;
use Lomkit\Rest\Metrics\ValueResult;

class PostCount extends Value
{
    public ?string $uriKey = 'post-count';

    #[\Override]
    public function calculate(Builder $query): ValueResult
    {
        return $this->count($query);
    }
}
