<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class MemoryEngine extends Engine
{
    /** @var array<string, array<string, array<string, mixed>>> */
    public array $documents = [];

    /** @var list<Builder<Model>> */
    public array $searches = [];

    /** @var list<list<string>> */
    public array $updates = [];

    /** @var list<array{string, list<string>}> */
    public array $indexUpdates = [];

    #[\Override]
    public function update($models): void
    {
        $this->updates[] = array_values(array_map(fn (Model $model): string => (string) $model->getScoutKey(), $models->all()));
        $this->indexUpdates[] = [(string) $models->first()?->searchableAs(), end($this->updates)];

        foreach ($models as $model) {
            $this->documents[$model->searchableAs()][(string) $model->getScoutKey()] = [
                ...$model->toSearchableArray(),
                ...$model->scoutMetadata(),
            ];
        }
    }

    #[\Override]
    public function delete($models): void
    {
        foreach ($models as $model) {
            unset($this->documents[$model->searchableAs()][(string) $model->getScoutKey()]);
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function search(Builder $builder): mixed
    {
        if ($builder->callback !== null) {
            return call_user_func($builder->callback, $this, $builder->query, []);
        }

        return $this->searchDocuments($builder);
    }

    /**
     * @param  Builder<Model>  $builder
     * @return array{hits: list<array<string, mixed>>, total: int}
     */
    protected function searchDocuments(Builder $builder): array
    {
        $this->searches[] = $builder;

        $hits = array_values(array_filter(
            $this->documents[$builder->index ?? $builder->model->searchableAs()] ?? [],
            fn (array $document): bool => $this->matches($builder, $document),
        ));

        return ['hits' => $hits, 'total' => count($hits)];
    }

    #[\Override]
    public function paginate(Builder $builder, $perPage, $page): mixed
    {
        $results = $this->search($builder);
        $results['hits'] = array_slice($results['hits'], ($page - 1) * $perPage, $perPage);

        return $results;
    }

    #[\Override]
    public function mapIds($results): \Illuminate\Support\Collection
    {
        return collect($results['hits'])->pluck('id')->values();
    }

    #[\Override]
    public function map(Builder $builder, $results, $model): Collection
    {
        $ids = $this->mapIds($results)->all();

        if ($ids === []) {
            return $model->newCollection();
        }

        return $model->getScoutModelsByIds($builder, $ids)
            ->sortBy(fn (Model $row): int|false => array_search((string) $row->getKey(), $ids, true))
            ->values();
    }

    #[\Override]
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return LazyCollection::make($this->map($builder, $results, $model)->all());
    }

    #[\Override]
    public function getTotalCount($results): int
    {
        return $results['total'];
    }

    #[\Override]
    public function flush($model): void
    {
        unset($this->documents[$model->searchableAs()]);
    }

    #[\Override]
    public function createIndex($name, array $options = []): void {}

    #[\Override]
    public function deleteIndex($name): void
    {
        unset($this->documents[$name]);
    }

    /** @param  array<string, mixed>  $document */
    protected function matches(Builder $builder, array $document): bool
    {
        if ($builder->query !== '' && stripos((string) ($document['title'] ?? ''), $builder->query) === false) {
            return false;
        }

        foreach ($builder->wheres as $where) {
            if (($document[$where['field']] ?? null) !== $where['value']) {
                return false;
            }
        }

        foreach ($builder->whereIns as $field => $values) {
            $indexed = $document[$field] ?? [];

            if (array_intersect(is_array($indexed) ? $indexed : [$indexed], $values) === []) {
                return false;
            }
        }

        return true;
    }
}
