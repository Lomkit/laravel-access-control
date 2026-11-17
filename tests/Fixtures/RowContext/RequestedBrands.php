<?php

namespace Lomkit\Access\Tests\Fixtures\RowContext;

use Closure;

final class RequestedBrands
{
    /** @var list<int> */
    private array $brands = [];

    /** @param  list<int>  $brands */
    public function request(array $brands): void
    {
        $this->brands = $brands;
    }

    /** @return list<int> */
    public function all(): array
    {
        return $this->brands;
    }

    /**
     * @template TResult
     *
     * @param  list<int>  $brands
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function actingIn(array $brands, Closure $callback): mixed
    {
        $previous = $this->brands;
        $this->brands = $brands;

        try {
            return $callback();
        } finally {
            $this->brands = $previous;
        }
    }

    /** @return array{brands: string} */
    public function decisionContext(): array
    {
        $brands = $this->brands;
        sort($brands);

        return ['brands' => implode(',', $brands)];
    }

    /**
     * @param  list<int>  $granted
     * @return list<int>
     */
    public function within(array $granted): array
    {
        return array_values(array_intersect($granted, $this->brands));
    }
}
