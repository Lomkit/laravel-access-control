<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\AlgoliaRules;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\Syntax\AlgoliaSyntax;

/** @internal */
final readonly class AlgoliaSearchGuard implements SearchGuard
{
    private const string SEARCHES = 'requests';

    private const string INDEX = 'indexName';

    private const string PARAMS = 'params';

    private const string FILTER = 'filters';

    private const array ENVELOPE = ['strategy'];

    private const array REQUEST = [self::INDEX => true, 'type' => true, 'facet' => true];

    private const array ALLOWED = [
        'query', 'similarQuery', self::FILTER, 'facetFilters', 'optionalFilters', 'numericFilters', 'tagFilters', 'sumOrFiltersScores',
        'restrictSearchableAttributes', 'facets', 'facetingAfterDistinct', 'maxValuesPerFacet', 'sortFacetValuesBy', 'facetQuery', 'maxFacetHits',
        'page', 'hitsPerPage', 'offset', 'length', 'aroundLatLng', 'aroundLatLngViaIP', 'aroundRadius', 'aroundPrecision', 'minimumAroundRadius',
        'insideBoundingBox', 'insidePolygon', 'naturalLanguages', 'queryLanguages', 'ignorePlurals', 'removeStopWords', 'decompoundQuery',
        'attributesToRetrieve', 'attributesToHighlight', 'attributesToSnippet', 'highlightPreTag', 'highlightPostTag', 'snippetEllipsisText',
        'restrictHighlightAndSnippetArrays', 'minWordSizefor1Typo', 'minWordSizefor2Typos', 'typoTolerance', 'allowTyposOnNumericTokens',
        'disableTypoToleranceOnAttributes', 'queryType', 'removeWordsIfNoResults', 'advancedSyntax', 'advancedSyntaxFeatures', 'optionalWords',
        'disableExactOnAttributes', 'exactOnSingleWordQuery', 'alternativesAsExact', 'distinct', 'getRankingInfo', 'synonyms',
        'replaceSynonymsInHighlight', 'minProximity', 'responseFields', 'percentileComputation', 'attributeCriteriaComputedByMinProximity',
        'relevancyStrictness', 'analytics', 'analyticsTags', 'clickAnalytics', 'userToken', AlgoliaRules::PARAMETER, AlgoliaRules::AB_TESTS,
    ];

    private SearchParameterRules $rules;

    public function __construct()
    {
        $this->rules = new SearchParameterRules($this->engine(), self::ALLOWED);
    }

    #[\Override]
    public function engine(): SearchTokenEngine
    {
        return SearchTokenEngine::Algolia;
    }

    #[\Override]
    public function searchesField(): string
    {
        return self::SEARCHES;
    }

    /** @param  array<array-key, mixed>  $fields */
    #[\Override]
    public function envelope(array $fields): array
    {
        foreach (array_keys($fields) as $field) {
            if (! in_array($field, self::ENVELOPE, true)) {
                throw InvalidSearchBodyException::unknownField($this->engine(), (string) $field);
            }
        }

        return $fields;
    }

    /** @param  array<array-key, mixed>  $search */
    #[\Override]
    public function index(array $search, int $position): string
    {
        $index = $search[self::INDEX] ?? null;

        return is_string($index) && $index !== '' ? $index : throw InvalidSearchBodyException::missingIndex($this->engine(), $position, self::INDEX);
    }

    /** @param  array<array-key, mixed>  $search */
    #[\Override]
    public function guard(array $search, string $index, SearchFilter $filter): array
    {
        $request = array_intersect_key($search, self::REQUEST);

        if (! array_key_exists(self::PARAMS, $search)) {
            return [...$request, ...$this->guardParameters(array_diff_key($search, self::REQUEST), $index, $filter, false)];
        }

        $query = $search[self::PARAMS];

        if (! is_string($query)) {
            throw InvalidSearchBodyException::invalidField($this->engine(), $index, self::PARAMS);
        }

        if (array_diff_key($search, self::REQUEST, [self::PARAMS => true]) !== []) {
            throw InvalidSearchBodyException::mixedParameters($this->engine(), $index);
        }

        $parameters = $this->guardParameters(AlgoliaParameters::parse($query, $index), $index, $filter, 'false');

        return [...$request, self::PARAMS => AlgoliaParameters::build($parameters)];
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     *
     * @throws RefusedSearchParameterException When a parameter is refused.
     * @throws RefusedSearchFilterException When the filters cannot be ANDed with the access filter.
     */
    private function guardParameters(array $parameters, string $index, SearchFilter $filter, bool|string $disabled): array
    {
        $this->rules->check($parameters, $index);

        $client = $this->clientFilter($parameters, $index);
        $access = (new AlgoliaSyntax)->render($filter);

        if ($access !== null) {
            $parameters[self::FILTER] = $client === null ? $access : $client->and($access);
        }

        $parameters[AlgoliaRules::PARAMETER] = $disabled;
        $parameters[AlgoliaRules::AB_TESTS] = $disabled;

        return $parameters;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     *
     * @throws RefusedSearchFilterException When the filters are not a string, hold a backslash, or are not a self-contained expression.
     */
    private function clientFilter(array $parameters, string $index): ?AlgoliaFilter
    {
        $filters = $parameters[self::FILTER] ?? null;

        if ($filters === null) {
            return null;
        }

        if (! is_string($filters)) {
            throw RefusedSearchFilterException::invalidType($this->engine(), $index, self::FILTER, 'a string');
        }

        if (AlgoliaFilter::escapes($filters)) {
            throw RefusedSearchFilterException::escaped($this->engine(), $index, self::FILTER);
        }

        return AlgoliaFilter::of($filters) ?? throw RefusedSearchFilterException::notSelfContained($this->engine(), $index, self::FILTER, $filters);
    }
}
