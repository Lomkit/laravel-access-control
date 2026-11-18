<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\SearchAccess;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\Syntax\MeilisearchSyntax;

/** @internal */
final readonly class MeilisearchSearchGuard implements SearchGuard
{
    private const string SEARCHES = 'queries';

    private const string INDEX = 'indexUid';

    private const string FILTER = 'filter';

    private const string FEDERATION = 'federation';

    private const array FEDERATION_FIELDS = ['limit', 'offset', 'page', 'hitsPerPage', 'facetsByIndex', 'mergeFacets', 'distinct', 'showPerformanceDetails', 'personalize'];

    private const string FEDERATION_OPTIONS = 'federationOptions';

    private const array FEDERATION_OPTIONS_FIELDS = ['weight', 'queryPosition'];

    private const array ALLOWED = [
        self::INDEX, 'q', 'offset', 'limit', 'page', 'hitsPerPage', 'attributesToRetrieve', 'attributesToCrop', 'cropLength', 'cropMarker',
        'attributesToHighlight', 'highlightPreTag', 'highlightPostTag', 'showMatchesPosition', self::FILTER, 'sort', 'distinct', 'facets',
        'matchingStrategy', 'attributesToSearchOn', 'rankingScoreThreshold', 'locales', 'hybrid', 'vector', 'retrieveVectors', 'media',
        'personalize', 'showRankingScore', 'showRankingScoreDetails', 'showPerformanceDetails', self::FEDERATION_OPTIONS,
    ];

    private SearchParameterRules $rules;

    public function __construct()
    {
        $this->rules = new SearchParameterRules($this->engine(), self::ALLOWED);
    }

    #[\Override]
    public function engine(): SearchTokenEngine
    {
        return SearchTokenEngine::Meilisearch;
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
        foreach ($fields as $field => $value) {
            if ($field !== self::FEDERATION) {
                throw InvalidSearchBodyException::unknownField($this->engine(), (string) $field);
            }

            foreach (is_array($value) ? array_keys($value) : [] as $option) {
                if (! in_array($option, self::FEDERATION_FIELDS, true)) {
                    throw InvalidSearchBodyException::unknownField($this->engine(), self::FEDERATION.'.'.$option);
                }
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
    public function guard(array $search, string $index, SearchAccess $reader): array
    {
        $this->rules->check($search, $index);
        $this->checkFederationOptions($search, $index);

        $client = $this->clientFilter($search, $index);
        $access = $reader->render(new MeilisearchSyntax);

        if ($access !== null) {
            $search[self::FILTER] = [$access, ...$client];
        }

        return $search;
    }

    /**
     * @param  array<array-key, mixed>  $search
     * @return list<string|list<string>>
     *
     * @throws RefusedSearchFilterException When the filter is neither a string nor a list of strings and lists of strings.
     */
    private function clientFilter(array $search, string $index): array
    {
        $filter = $search[self::FILTER] ?? null;

        if ($filter === null || $filter === '' || $filter === []) {
            return [];
        }

        if (is_string($filter)) {
            return [$filter];
        }

        if (! is_array($filter) || ! array_is_list($filter)) {
            throw RefusedSearchFilterException::invalidType($this->engine(), $index, self::FILTER, 'a string, or a list of strings and lists of strings');
        }

        $clauses = [];

        foreach ($filter as $clause) {
            $clauses[] = is_string($clause) ? $clause : $this->disjunction($clause, $index);
        }

        return $clauses;
    }

    /**
     * @return list<string>
     *
     * @throws RefusedSearchFilterException When the clause is not a list of strings.
     */
    private function disjunction(mixed $clause, string $index): array
    {
        if (! is_array($clause) || ! array_is_list($clause)) {
            throw RefusedSearchFilterException::invalidType($this->engine(), $index, self::FILTER, 'a string, or a list of strings and lists of strings');
        }

        $strings = [];

        foreach ($clause as $value) {
            $strings[] = is_string($value) ? $value : throw RefusedSearchFilterException::invalidType($this->engine(), $index, self::FILTER, 'a string, or a list of strings and lists of strings');
        }

        return $strings;
    }

    /**
     * @param  array<array-key, mixed>  $search
     *
     * @throws InvalidSearchBodyException When the federation options are not an object.
     * @throws RefusedSearchParameterException When the federation options hold another field than the weight and the position of the query.
     */
    private function checkFederationOptions(array $search, string $index): void
    {
        $options = $search[self::FEDERATION_OPTIONS] ?? null;

        if ($options !== null && ! is_array($options)) {
            throw InvalidSearchBodyException::invalidField($this->engine(), $index, self::FEDERATION_OPTIONS);
        }

        foreach (is_array($options) ? array_keys($options) : [] as $option) {
            if (! in_array($option, self::FEDERATION_OPTIONS_FIELDS, true)) {
                throw RefusedSearchParameterException::named($this->engine(), $index, self::FEDERATION_OPTIONS.'.'.$option);
            }
        }
    }
}
