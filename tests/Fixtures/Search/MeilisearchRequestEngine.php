<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Laravel\Scout\Engines\MeilisearchEngine;

class MeilisearchRequestEngine extends MeilisearchEngine
{
    public function __construct(public RecordingSearchClient $client)
    {
        $this->meilisearch = $client;
        $this->softDelete = false;
        $this->config = [];
    }
}
