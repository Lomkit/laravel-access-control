<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Access\Exceptions\NaturalLanguageSearchException;

/** @internal */
final readonly class TypesenseNaturalLanguage
{
    private const string OPTION = 'nl_query';

    private const array OFF = [null, false, 'false'];

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws NaturalLanguageSearchException When the options or the model turn on natural-language search.
     */
    public static function refuse(Builder $search): void
    {
        if (! in_array($search->options[self::OPTION] ?? null, self::OFF, true)) {
            throw NaturalLanguageSearchException::typesense($search->model, 'options()');
        }

        $parameters = method_exists($search->model, 'typesenseSearchParameters') ? $search->model->typesenseSearchParameters() : [];

        if (is_array($parameters) && ! in_array($parameters[self::OPTION] ?? null, self::OFF, true)) {
            throw NaturalLanguageSearchException::typesense($search->model, 'typesenseSearchParameters()');
        }
    }
}
