<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;

class TypesenseRequestEngine extends TypesenseEngine
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    public function __construct()
    {
        $this->maxTotalResults = 1000;
        $this->config = [];
    }

    /** @param  array<string, mixed>  $options */
    #[\Override]
    protected function performSearch(Builder $builder, array $options = []): mixed
    {
        $this->requests[] = $options;

        if ($builder->callback !== null) {
            return call_user_func($builder->callback, null, $builder->query, $options);
        }

        return ['hits' => [], 'found' => 0, 'out_of' => 0, 'page' => $options['page'] ?? 1];
    }
}
