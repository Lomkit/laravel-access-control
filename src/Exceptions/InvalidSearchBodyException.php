<?php

namespace Lomkit\Access\Exceptions;

use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvalidSearchBodyException extends UnprocessableEntityHttpException
{
    /** @param  list<string>  $fields */
    public static function shape(array $fields): self
    {
        return new self(sprintf(
            'The search body must hold exactly one list of searches, under one of the fields [%s]: the body of a Typesense multi_search ({"searches": [...]}), a Meilisearch multi-search ({"queries": [...]}) or Algolia multiple queries ({"requests": [...]}).',
            implode(', ', $fields),
        ));
    }

    public static function notAList(SearchTokenEngine $engine, string $field): self
    {
        return new self(sprintf('The [%s] field of the %s search body must be a list of searches.', $field, $engine->label()));
    }

    public static function notAnObject(SearchTokenEngine $engine, string $field, int $position): self
    {
        return new self(sprintf('The search [%d] of the [%s] field of the %s search body must be an object of search parameters.', $position, $field, $engine->label()));
    }

    public static function missingIndex(SearchTokenEngine $engine, int $position, string $indexField): self
    {
        return new self(sprintf('The search [%d] of the %s search body must name its index in [%s], as a non-empty string.', $position, $engine->label(), $indexField));
    }

    public static function unknownField(SearchTokenEngine $engine, string $field): self
    {
        return new self(sprintf('The %s search body holds the field [%s], which a guarded search does not accept. Send the list of searches and the fields the package knows only.', $engine->label(), $field));
    }

    public static function invalidField(SearchTokenEngine $engine, string $index, string $field): self
    {
        return new self(sprintf('The %s search of the [%s] index holds a [%s] of an unexpected type.', $engine->label(), $index, $field));
    }

    public static function mixedParameters(SearchTokenEngine $engine, string $index): self
    {
        return new self(sprintf('The %s search of the [%s] index sends its parameters both in [params] and as fields of the search. Send them in one form only.', $engine->label(), $index));
    }

    public static function unparsableParameters(SearchTokenEngine $engine, string $index): self
    {
        return new self(sprintf('The [params] of the %s search of the [%s] index is not a query string of distinct, named parameters.', $engine->label(), $index));
    }

    public static function mismatchedEngine(SearchTokenEngine $body, string $index, SearchTokenEngine $engine): self
    {
        return new self(sprintf('The search body has the shape of a %s search, but the [%s] index is searched with %s. Send each search to the engine of its index.', $body->label(), $index, $engine->label()));
    }
}
