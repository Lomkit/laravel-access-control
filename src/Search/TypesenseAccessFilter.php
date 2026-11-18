<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Access\Exceptions\UncombinableSearchFilterException;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;

final class TypesenseAccessFilter
{
    private const string FILTER = 'filter_by';

    private ?string $applied = null;

    private ?TypesenseAppFilter $app = null;

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UncombinableSearchFilterException When the filter_by of the app cannot be ANDed with the access filter.
     */
    public function apply(Builder $search, SearchFilter $filter): void
    {
        $access = (new TypesenseSyntax)->render($filter);

        if ($access === null) {
            return;
        }

        if ($this->applied === null || ($search->options[self::FILTER] ?? null) !== $this->applied) {
            $this->app = TypesenseAppFilter::of($search);
        }

        $this->applied = $this->app?->and($access);

        if ($this->applied !== null) {
            $search->options[self::FILTER] = $this->applied;
        }
    }
}
