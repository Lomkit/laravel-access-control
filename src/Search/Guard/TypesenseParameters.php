<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Lomkit\Access\Search\TypesenseFilterTokenizer;

/** @internal */
final readonly class TypesenseParameters
{
    public const array ALLOWED = [
        'collection', 'q', 'query_by', 'query_by_weights', 'prefix', 'infix', 'max_extra_prefix', 'max_extra_suffix', 'pre_segmented_query',
        'split_join_tokens', 'text_match_type', 'num_typos', 'min_len_1typo', 'min_len_2typo', 'typo_tokens_threshold', 'drop_tokens_threshold',
        'drop_tokens_mode', 'enable_typos_for_numerical_tokens', 'enable_typos_for_alpha_numerical_tokens', 'synonym_prefix', 'synonym_num_typos',
        'enable_synonyms', 'demote_synonym_match', 'synonym_sets', 'stopwords', 'filter_by', 'filter_curated_hits', 'enable_lazy_filter',
        'max_filter_by_candidates', 'validate_field_names', 'hidden_hits', 'sort_by', 'prioritize_exact_match', 'prioritize_token_position',
        'prioritize_num_matching_fields', 'facet_by', 'facet_query', 'facet_query_num_typos', 'max_facet_values', 'facet_strategy',
        'facet_sample_percent', 'facet_sample_threshold', 'facet_sample_slope', 'facet_return_parent', 'group_by', 'group_limit',
        'group_missing_values', 'group_max_candidates', 'page', 'per_page', 'offset', 'limit', 'limit_hits', 'include_fields', 'exclude_fields',
        'highlight_fields', 'highlight_full_fields', 'highlight_affix_num_tokens', 'highlight_start_tag', 'highlight_end_tag', 'snippet_threshold',
        'exhaustive_search', 'search_cutoff_ms', 'max_candidates', 'use_cache', 'cache_ttl', 'vector_query', 'rerank_hybrid_matches',
        'remote_embedding_timeout_ms', 'remote_embedding_num_tries', 'voice_query',
    ];

    private const array FIELD_LISTS = ['include_fields', 'exclude_fields', 'sort_by', 'facet_by', 'group_by'];

    private const string REFERENCE = '$';

    private const string VECTOR = 'vector_query';

    private const string DOCUMENT_VECTOR = '/(?:^|,)\s*id\s*:/i';

    /**
     * @param  array<array-key, mixed>  $search
     *
     * @throws RefusedSearchParameterException When the filter or a field list references another collection, or the vector query reads a stored document.
     */
    public static function check(array $search, string $index): void
    {
        $filter = $search['filter_by'] ?? null;

        if (is_string($filter) && self::references($filter)) {
            throw RefusedSearchParameterException::joined(SearchTokenEngine::Typesense, $index, 'filter_by');
        }

        foreach (self::FIELD_LISTS as $parameter) {
            $value = $search[$parameter] ?? null;

            if (is_string($value) && str_contains($value, self::REFERENCE)) {
                throw RefusedSearchParameterException::joined(SearchTokenEngine::Typesense, $index, $parameter);
            }
        }

        $vector = $search[self::VECTOR] ?? null;

        if (is_string($vector) && preg_match(self::DOCUMENT_VECTOR, (string) strstr($vector, ']')) === 1) {
            throw RefusedSearchParameterException::documentVector(SearchTokenEngine::Typesense, $index);
        }
    }

    private static function references(string $filter): bool
    {
        foreach (TypesenseFilterTokenizer::tokenize($filter) ?? [] as $token) {
            if (str_starts_with(ltrim($token, '!'), self::REFERENCE)) {
                return true;
            }
        }

        return false;
    }
}
