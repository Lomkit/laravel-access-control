<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

class RecordingSearchClient
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    public function index(string $name): self
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function rawSearch(string $query, array $parameters): array
    {
        $this->requests[] = $parameters;

        return ['hits' => [], 'totalHits' => 0, 'estimatedTotalHits' => 0];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function searchSingleIndex(string $index, array $parameters): array
    {
        $this->requests[] = $parameters;

        return ['hits' => [], 'nbHits' => 0];
    }
}
