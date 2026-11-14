<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;

/** @internal */
final class SearchableRows
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<Builder<Model>>  $rows
     *
     * @throws MissingControlledSearchableException When the model is not searchable.
     */
    public static function reindex(string $model, array $rows): void
    {
        $searchable = new $model;

        if (! method_exists($searchable, 'queueMakeSearchable') || ! method_exists($searchable, 'queueRemoveFromSearch') || ! method_exists($searchable, 'getScoutKeyName')) {
            throw MissingControlledSearchableException::forReindex($model);
        }

        if ($rows === []) {
            return;
        }

        $scoutKey = $searchable->getScoutKeyName();
        $keyName = is_string($scoutKey) ? $scoutKey : $searchable->getKeyName();

        self::documents($searchable, $rows)->chunkById(
            ScoutOptions::chunk(),
            function (Collection $models) use ($searchable): void {
                [$kept, $removed] = $models->partition(self::shouldBeSearchable(...));

                $searchable->queueMakeSearchable($kept);
                $searchable->queueRemoveFromSearch($removed);
            },
            $searchable->qualifyColumn($keyName),
            $keyName,
        );
    }

    /**
     * @param  list<Builder<Model>>  $rows
     * @return Builder<Model>
     */
    private static function documents(Model $searchable, array $rows): Builder
    {
        $key = $searchable->getQualifiedKeyName();
        $query = $searchable->newQueryWithoutScopes()->where(function (Builder $query) use ($rows, $key): void {
            foreach ($rows as $row) {
                $query->orWhereIn($key, $row->withoutGlobalScopes()->select($key));
            }
        });

        $deletedAt = method_exists($searchable, 'getQualifiedDeletedAtColumn') ? $searchable->getQualifiedDeletedAtColumn() : null;

        if (is_string($deletedAt) && ! ScoutOptions::keepsSoftDeleted()) {
            $query->whereNull($deletedAt);
        }

        return $query;
    }

    private static function shouldBeSearchable(Model $model): bool
    {
        return ! method_exists($model, 'shouldBeSearchable') || $model->shouldBeSearchable() === true;
    }
}
