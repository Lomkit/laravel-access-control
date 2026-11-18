<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Laravel\Scout\Engines\Algolia4Engine;

class AlgoliaRequestEngine extends Algolia4Engine
{
    public function __construct(public RecordingSearchClient $client)
    {
        $this->algolia = $client;
        $this->softDelete = false;
    }
}
