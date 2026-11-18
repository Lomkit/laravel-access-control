<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;
use Typesense\Documents;

class MultiSearchTypesenseEngine extends TypesenseEngine
{
    /** @param  array<string, mixed>  $options */
    #[\Override]
    protected function executeSearch(Builder $builder, Documents $documents, array $options): mixed
    {
        $results = $this->typesense->getMultiSearch()->perform([
            'searches' => [[...$options, 'collection' => $builder->index ?? $builder->model->searchableAs()]],
        ]);

        $result = $results['results'][0] ?? [];

        if (isset($result['error'])) {
            throw $this->marshalMultiSearchException($result);
        }

        return $result;
    }
}
