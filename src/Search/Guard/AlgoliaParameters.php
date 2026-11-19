<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

/** @internal */
final readonly class AlgoliaParameters
{
    /**
     * @return array<string, string>
     *
     * @throws InvalidSearchBodyException When a parameter has no name, or a name comes twice whatever its case.
     */
    public static function parse(string $query, string $index): array
    {
        $parameters = [];
        $names = [];

        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);

            if ($name === '' || array_key_exists(strtolower($name), $names)) {
                throw InvalidSearchBodyException::unparsableParameters(SearchTokenEngine::Algolia, $index);
            }

            $names[strtolower($name)] = true;
            $parameters[$name] = urldecode($value);
        }

        return $parameters;
    }

    /** @param  array<array-key, mixed>  $parameters */
    public static function build(array $parameters): string
    {
        return http_build_query($parameters, encoding_type: PHP_QUERY_RFC3986);
    }
}
