<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Access\Search\Freshness\ReindexWhen;
use Lomkit\Access\Search\ReindexesSearch;
use Lomkit\Access\Search\SearchKeys;

/** @internal */
final class KeyRelations
{
    private const string COLUMNS = ':';

    private const string COLUMN_SEPARATOR = ',';

    private const string NESTING = '.';

    /**
     * @param  class-string<Model>  $model
     * @param  list<ReindexWhen>  $reindexes
     * @return list<UnwatchedKeyRelation>
     */
    public static function unwatched(string $model, SearchKeys $keys, array $reindexes): array
    {
        $issues = [];

        foreach ($keys->load() as $path) {
            $relations = strstr($path, self::COLUMNS, true);
            $columns = $relations === false ? [] : explode(self::COLUMN_SEPARATOR, substr($path, strlen($relations) + 1));

            array_push($issues, ...self::unwatchedAlong($model, $keys, $reindexes, explode(self::NESTING, $relations ?: $path), $columns));
        }

        return $issues;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<ReindexWhen>  $reindexes
     * @param  list<string>  $segments
     * @param  list<string>  $columns
     * @return list<UnwatchedKeyRelation>
     */
    private static function unwatchedAlong(string $model, SearchKeys $keys, array $reindexes, array $segments, array $columns): array
    {
        $issues = [];
        $parent = new $model;
        $walked = [];

        foreach ($segments as $position => $segment) {
            $walked[] = $segment;
            $relation = method_exists($parent, $segment) ? $parent->{$segment}() : null;

            if (! $relation instanceof Relation) {
                return [...$issues, new UnwatchedKeyRelation($model, $keys->name(), implode(self::NESTING, $walked), null)];
            }

            $parent = $relation->getRelated();
            $read = $position === array_key_last($segments) && $columns !== [] ? array_values(array_diff($columns, [$parent->getKeyName(), $parent->getQualifiedKeyName()])) : null;

            if (! self::watched($parent, $read, $reindexes) && ! ($read === [] && self::reindexedByItsPivot($relation))) {
                $issues[] = new UnwatchedKeyRelation($model, $keys->name(), implode(self::NESTING, $walked), $parent::class);
            }
        }

        return $issues;
    }

    /**
     * @param  list<string>|null  $read
     * @param  list<ReindexWhen>  $reindexes
     */
    private static function watched(Model $related, ?array $read, array $reindexes): bool
    {
        foreach ($reindexes as $reindex) {
            if ($reindex->watches($related) && ($read === null || $read === [] || array_diff($read, $reindex->changed()) === [])) {
                return true;
            }
        }

        return false;
    }

    /** @param  Relation<Model, Model, mixed>  $relation */
    private static function reindexedByItsPivot(Relation $relation): bool
    {
        return $relation instanceof BelongsToMany
            && in_array(ReindexesSearch::class, class_uses_recursive($relation->getPivotClass()), true);
    }
}
