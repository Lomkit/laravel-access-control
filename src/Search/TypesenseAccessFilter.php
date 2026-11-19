<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Exceptions\NaturalLanguageSearchException;
use Lomkit\Access\Exceptions\UncombinableSearchFilterException;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;

/** @internal */
final class TypesenseAccessFilter
{
    private const string FILTER = 'filter_by';

    private const string CURATED_HITS = 'filter_curated_hits';

    /** @var array<string, TypesenseAppFilter|null> */
    private array $options = [];

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UncombinableSearchFilterException When the filter of the app cannot be ANDed with the access filter.
     * @throws NaturalLanguageSearchException When the options or the model turn on natural-language search.
     */
    public function apply(Builder $search, SearchFilter $filter, TypesenseEngine $engine): void
    {
        $access = (new TypesenseSyntax)->render($filter);

        if ($access === null) {
            return;
        }

        TypesenseNaturalLanguage::refuse($search);

        $option = $this->optionOf($search);
        $app = $option ?? TypesenseAppFilter::fromModel($search) ?? TypesenseAppFilter::fromClauses($search, $engine);
        $applied = $app->and($access);

        $this->options[$applied] = $option;
        $search->options[self::FILTER] = $applied;
        $search->options[self::CURATED_HITS] = true;
    }

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UncombinableSearchFilterException When the filter_by of the options is not a string, or does not end on its own.
     */
    private function optionOf(Builder $search): ?TypesenseAppFilter
    {
        if (! array_key_exists(self::FILTER, $search->options)) {
            return null;
        }

        $current = $search->options[self::FILTER];

        if (is_string($current) && array_key_exists($current, $this->options)) {
            return $this->options[$current];
        }

        return TypesenseAppFilter::fromOptions($search->model, $current);
    }
}
