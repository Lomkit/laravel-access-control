<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

/** @internal */
final readonly class SearchParameterRules
{
    /** @param  list<string>  $allowed */
    public function __construct(
        private SearchTokenEngine $engine,
        private array $allowed,
    ) {}

    /**
     * @param  array<array-key, mixed>  $parameters
     *
     * @throws RefusedSearchParameterException When a parameter is not one the guard accepts, as written.
     */
    public function check(array $parameters, string $index): void
    {
        foreach (array_keys($parameters) as $name) {
            if (! in_array($name, $this->allowed, true)) {
                throw RefusedSearchParameterException::named($this->engine, $index, (string) $name);
            }
        }
    }
}
