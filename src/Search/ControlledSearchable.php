<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingControlException;

trait ControlledSearchable
{
    use Searchable;

    /**
     * @param  Collection<array-key, static>  $models
     * @return Collection<array-key, static>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $this->withAccessKeys($models);
    }

    /** @return ControlledSearchBuilder<static> */
    public static function search(string $query = '', ?Closure $callback = null): ControlledSearchBuilder
    {
        return new ControlledSearchBuilder(static::query()->getModel(), $query, $callback, static::usesSoftDelete() && (bool) config('scout.soft_delete', false));
    }

    /**
     * @param  Collection<array-key, static>  $models
     * @return Collection<array-key, static>
     *
     * @throws MissingControlException When no control is registered for the model.
     */
    protected function withAccessKeys(Collection $models): Collection
    {
        if ($models->isEmpty() || ! SearchEngineKind::storesDocuments($this->searchableUsing())) {
            return $models;
        }

        $rows = array_values($models->all());
        $documents = new SearchDocuments(Access::controlFor(static::class)->searchRules());

        foreach ($documents->metadataFor($rows) as $index => $metadata) {
            foreach ($metadata as $field => $value) {
                $rows[$index]->withScoutMetadata($field, $value);
            }
        }

        return $models;
    }
}
