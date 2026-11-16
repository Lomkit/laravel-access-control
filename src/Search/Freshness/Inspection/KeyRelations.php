<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Relations\RelationInspector;
use Lomkit\Access\Relations\RelationShape;
use Lomkit\Access\Search\Freshness\ReindexWhen;
use Lomkit\Access\Search\ReindexesSearch;
use Lomkit\Access\Search\SearchKeys;
use Throwable;

/** @internal */
final class KeyRelations
{
    private const string COLUMNS = ':';

    private const string COLUMN_SEPARATOR = ',';

    private const string NESTING = '.';

    /**
     * @param  class-string<Model>  $model
     * @param  list<ReindexWhen>  $reindexes
     * @return list<FreshnessIssue>
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
     * @return list<FreshnessIssue>
     */
    private static function unwatchedAlong(string $model, SearchKeys $keys, array $reindexes, array $segments, array $columns): array
    {
        $issues = [];
        $parent = new $model;
        $walked = [];

        foreach ($segments as $position => $segment) {
            $walked[] = $segment;

            try {
                $shape = method_exists($parent, $segment) ? RelationInspector::shape($parent, $segment) : null;
            } catch (Throwable $exception) {
                return [...$issues, new UnreadableKeyRelation($model, $keys->name(), $parent::class, $segment, $exception->getMessage())];
            }

            if ($shape === null) {
                return [...$issues, new UnwatchedKeyRelation($model, $keys->name(), implode(self::NESTING, $walked), null)];
            }

            $related = $shape->related();
            $parent = new $related;
            $read = $position === array_key_last($segments) && $columns !== [] ? array_values(array_diff($columns, [$parent->getKeyName(), $parent->getQualifiedKeyName()])) : null;

            if (! self::watched($parent, $read, $reindexes) && ! ($read === [] && self::reindexedByItsPivot($shape))) {
                $issues[] = new UnwatchedKeyRelation($model, $keys->name(), implode(self::NESTING, $walked), $related);
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

    private static function reindexedByItsPivot(RelationShape $shape): bool
    {
        $pivot = $shape->pivot();

        return $pivot !== null && in_array(ReindexesSearch::class, class_uses_recursive($pivot->class()), true);
    }
}
