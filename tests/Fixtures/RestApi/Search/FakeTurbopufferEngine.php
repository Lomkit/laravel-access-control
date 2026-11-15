<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TurbopufferEngine;

class FakeTurbopufferEngine extends TurbopufferEngine
{
    /** @var list<Builder<Model>> */
    public array $searches = [];

    public function __construct() {}

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page): array
    {
        $this->searches[] = $builder;

        return ['rows' => [], 'total' => 0];
    }
}
