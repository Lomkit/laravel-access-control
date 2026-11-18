<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

/** @internal */
final class ContainsAnyFilter
{
    private const string OPTION = 'filters';

    private mixed $native = null;

    private mixed $applied = null;

    /** @param  Builder<covariant Model>  $search */
    public function apply(Builder $search, SearchFilter $filter): void
    {
        $current = $search->options[self::OPTION] ?? null;

        if ($this->applied === null || $current !== $this->applied) {
            $this->native = $current;
        }

        $this->applied = self::combine($this->native, self::clauses($filter));

        if ($this->applied !== null) {
            $search->options[self::OPTION] = $this->applied;
        }
    }

    /** @return list<mixed>|null */
    private static function clauses(SearchFilter $filter): ?array
    {
        $clauses = [];

        foreach ($filter->whereIns() as $field => $values) {
            $clauses[] = [$field, 'ContainsAny', $values];
        }

        return match (count($clauses)) {
            0 => null,
            1 => $clauses[0],
            default => ['And', $clauses],
        };
    }

    /** @param  list<mixed>|null  $clauses */
    private static function combine(mixed $native, ?array $clauses): mixed
    {
        if ($native === null || $clauses === null) {
            return $native ?? $clauses;
        }

        return ['And', [$native, $clauses]];
    }
}
