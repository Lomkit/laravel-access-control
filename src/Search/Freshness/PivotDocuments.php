<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\UnresolvablePivotException;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final class PivotDocuments
{
    /** @throws UnresolvablePivotException When the pivot was written outside its relation. */
    public static function ensureResolvable(Pivot $pivot): void
    {
        self::sides($pivot);
    }

    /** @throws UnresolvablePivotException When the pivot was written outside its relation. */
    public static function reindex(Pivot $pivot): void
    {
        [$parent, $related] = self::sides($pivot);

        self::push($pivot, $parent, $pivot->getForeignKey());
        self::push($pivot, $related, $pivot->getRelatedKey());
    }

    /**
     * @return array{Model, Model}
     *
     * @throws UnresolvablePivotException When the pivot was written outside its relation.
     * @throws MissingControlledSearchableException When neither linked model is searchable.
     */
    private static function sides(Pivot $pivot): array
    {
        $sides = get_object_vars($pivot);
        $parent = $sides['pivotParent'] ?? null;
        $related = $sides['pivotRelated'] ?? null;

        if (! $parent instanceof Model || ! $related instanceof Model) {
            throw UnresolvablePivotException::for($pivot::class);
        }

        if (! UsesControlledSearchable::check($parent) && ! UsesControlledSearchable::check($related)) {
            throw MissingControlledSearchableException::forPivot($pivot::class, $parent::class, $related::class);
        }

        return [$parent, $related];
    }

    private static function push(Pivot $pivot, Model $side, string $column): void
    {
        if (! UsesControlledSearchable::check($side)) {
            return;
        }

        $pushed = [];

        foreach ([$pivot->getAttribute($column), $pivot->getOriginal($column)] as $key) {
            if ((is_int($key) || is_string($key)) && ! in_array((string) $key, $pushed, true)) {
                $pushed[] = (string) $key;
                SearchQueue::push(new ReindexDocument($side::class, $key), $side, $pivot->getConnection());
            }
        }
    }
}
