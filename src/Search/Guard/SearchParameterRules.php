<?php

namespace Lomkit\Access\Search\Guard;

use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

/** @internal */
final readonly class SearchParameterRules
{
    /**
     * @param  list<string>  $refused
     * @param  list<string>  $handled
     */
    public function __construct(
        private SearchTokenEngine $engine,
        private array $refused,
        private array $handled,
        private ?string $keyPrefix = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $parameters
     *
     * @throws RefusedSearchParameterException When a parameter is refused, carries a key, or differs from a parameter the guard sets by its case only.
     */
    public function check(array $parameters, string $index): void
    {
        foreach (array_keys($parameters) as $name) {
            $name = (string) $name;

            if ($this->refuses($name)) {
                throw RefusedSearchParameterException::named($this->engine, $index, $name);
            }
        }
    }

    private function refuses(string $name): bool
    {
        $lower = strtolower($name);

        if ($this->keyPrefix !== null && str_starts_with($lower, $this->keyPrefix)) {
            return true;
        }

        foreach ($this->refused as $refused) {
            if ($lower === strtolower($refused)) {
                return true;
            }
        }

        foreach ($this->handled as $handled) {
            if ($lower === strtolower($handled) && $name !== $handled) {
                return true;
            }
        }

        return false;
    }
}
