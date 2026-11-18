<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

/** @internal */
interface SearchGuard
{
    public function engine(): SearchTokenEngine;

    public function searchesField(): string;

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<array-key, mixed>
     *
     * @throws InvalidSearchBodyException When a field besides the searches is not one the engine combines safely.
     */
    public function envelope(array $fields): array;

    /**
     * @param  array<array-key, mixed>  $search
     *
     * @throws InvalidSearchBodyException When the search does not name its index.
     */
    public function index(array $search, int $position): string;

    /**
     * @param  array<array-key, mixed>  $search
     * @return array<array-key, mixed>
     *
     * @throws InvalidSearchBodyException When the parameters of the search are malformed.
     * @throws RefusedSearchParameterException When the search sends a parameter that a guarded search refuses.
     * @throws RefusedSearchFilterException When the filter of the search cannot be ANDed with the access filter.
     */
    public function guard(array $search, string $index, SearchFilter $filter): array;
}
