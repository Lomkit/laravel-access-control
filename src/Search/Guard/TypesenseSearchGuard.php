<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;
use Lomkit\Access\Search\TypesenseFilterBalance;
use Lomkit\Access\Search\TypesenseFilterTokenizer;

/** @internal */
final readonly class TypesenseSearchGuard implements SearchGuard
{
    private const string SEARCHES = 'searches';

    private const string COLLECTION = 'collection';

    private const string FILTER = 'filter_by';

    private const string CURATED_HITS = 'filter_curated_hits';

    private const array ENVELOPE = ['union'];

    private const array REFUSED = ['pinned_hits', 'preset', 'enable_overrides', 'override_tags', 'enable_curations', 'curation_tags', 'conversation_id'];

    private const array FIELD_LISTS = ['include_fields', 'exclude_fields', 'sort_by', 'facet_by', 'group_by'];

    private const string KEY_PREFIX = 'x-typesense-';

    private const string JOIN = '/\$[^\s(]*\s*\(/';

    private SearchParameterRules $rules;

    public function __construct()
    {
        $this->rules = new SearchParameterRules($this->engine(), self::REFUSED, [self::COLLECTION, self::FILTER, self::CURATED_HITS], self::KEY_PREFIX);
    }

    #[\Override]
    public function engine(): SearchTokenEngine
    {
        return SearchTokenEngine::Typesense;
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
        $index = $search[self::COLLECTION] ?? null;

        return is_string($index) && $index !== '' ? $index : throw InvalidSearchBodyException::missingIndex($this->engine(), $position, self::COLLECTION);
    }

    /** @param  array<array-key, mixed>  $search */
    #[\Override]
    public function guard(array $search, string $index, SearchFilter $filter): array
    {
        $this->rules->check($search, $index);
        $client = $this->clientFilter($search, $index);
        $this->refuseJoins($search, $index);

        $access = (new TypesenseSyntax)->render($filter);
        $combined = $access === null || $client === null ? $client ?? $access : $this->and($client, $access, $index);

        unset($search[self::FILTER]);

        if ($combined !== null) {
            $search[self::FILTER] = $combined;
        }

        $search[self::CURATED_HITS] = true;

        return $search;
    }

    /**
     * @param  array<array-key, mixed>  $search
     *
     * @throws RefusedSearchFilterException When the filter is not a string, or not a self-contained expression.
     */
    private function clientFilter(array $search, string $index): ?string
    {
        if (! array_key_exists(self::FILTER, $search)) {
            return null;
        }

        $filter = $search[self::FILTER];

        if (! is_string($filter)) {
            throw RefusedSearchFilterException::invalidType($this->engine(), $index, self::FILTER, 'a string');
        }

        if (! TypesenseFilterBalance::isSelfContained($filter)) {
            throw RefusedSearchFilterException::notSelfContained($this->engine(), $index, self::FILTER, $filter);
        }

        return trim($filter) === '' ? null : $filter;
    }

    /**
     * @param  array<array-key, mixed>  $search
     *
     * @throws RefusedSearchParameterException When the filter or a field list references another collection.
     */
    private function refuseJoins(array $search, string $index): void
    {
        $filter = $search[self::FILTER] ?? null;

        if (is_string($filter) && $this->references($filter)) {
            throw RefusedSearchParameterException::joined($this->engine(), $index, self::FILTER);
        }

        foreach (self::FIELD_LISTS as $parameter) {
            $value = $search[$parameter] ?? null;

            if (is_string($value) && preg_match(self::JOIN, $value) === 1) {
                throw RefusedSearchParameterException::joined($this->engine(), $index, $parameter);
            }
        }
    }

    /** @throws RefusedSearchFilterException When the combined filter would not end with the access filter. */
    private function and(string $client, string $access, string $index): string
    {
        $combined = "({$client}) && ({$access})";

        return TypesenseFilterBalance::endsWithGroup($combined, $access) ? $combined : throw RefusedSearchFilterException::notSelfContained($this->engine(), $index, self::FILTER, $client);
    }

    private function references(string $filter): bool
    {
        foreach (TypesenseFilterTokenizer::tokenize($filter) ?? [] as $token) {
            if (str_starts_with(ltrim($token, '!'), '$')) {
                return true;
            }
        }

        return false;
    }
}
