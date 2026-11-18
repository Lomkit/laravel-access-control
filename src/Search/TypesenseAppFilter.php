<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Access\Exceptions\UncombinableSearchFilterException;

final readonly class TypesenseAppFilter
{
    private const string OPTION = 'filter_by';

    private function __construct(private string $expression) {}

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UncombinableSearchFilterException When the filter_by of the app is not a string, or not a self-contained expression.
     */
    public static function of(Builder $search): ?self
    {
        if (array_key_exists(self::OPTION, $search->options)) {
            return self::checked($search->model, 'options()', $search->options[self::OPTION]);
        }

        $parameters = method_exists($search->model, 'typesenseSearchParameters') ? $search->model->typesenseSearchParameters() : [];

        if (is_array($parameters) && array_key_exists(self::OPTION, $parameters)) {
            return self::checked($search->model, 'typesenseSearchParameters()', $parameters[self::OPTION]);
        }

        return null;
    }

    public function and(string $access): string
    {
        if (trim($this->expression) === '') {
            return $access;
        }

        return "({$this->expression}) && ({$access})";
    }

    /** @throws UncombinableSearchFilterException When the filter is not a string, or not a self-contained expression. */
    private static function checked(Model $model, string $source, mixed $filter): self
    {
        if (! is_string($filter)) {
            throw UncombinableSearchFilterException::notAString($model, $source, $filter);
        }

        if (! TypesenseFilterBalance::isSelfContained($filter)) {
            throw UncombinableSearchFilterException::notSelfContained($model, $source, $filter);
        }

        return new self($filter);
    }
}
