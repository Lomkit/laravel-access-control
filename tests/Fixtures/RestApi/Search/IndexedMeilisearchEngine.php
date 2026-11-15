<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;

class IndexedMeilisearchEngine extends MeilisearchEngine
{
    /** @var list<Builder<Model>> */
    public array $searches = [];

    public function __construct(private readonly MemoryEngine $memory) {}

    #[\Override]
    public function update($models): void
    {
        $this->memory->update($models);
    }

    #[\Override]
    public function delete($models): void
    {
        $this->memory->delete($models);
    }

    /** @return array{hits: list<array<string, mixed>>, totalHits: int} */
    #[\Override]
    public function search(Builder $builder): array
    {
        $this->searches[] = $builder;

        $hits = array_values(array_filter(
            $this->memory->documents[$builder->index ?? $builder->model->searchableAs()] ?? [],
            fn (array $document): bool => $this->matches($builder, $document),
        ));

        return ['hits' => $hits, 'totalHits' => count($hits)];
    }

    /** @return array{hits: list<array<string, mixed>>, totalHits: int} */
    #[\Override]
    public function paginate(Builder $builder, $perPage, $page): array
    {
        $results = $this->search($builder);
        $results['hits'] = array_slice($results['hits'], ($page - 1) * $perPage, $perPage);

        return $results;
    }

    #[\Override]
    public function map(Builder $builder, $results, $model): Collection
    {
        return $this->memory->map($builder, ['hits' => $results['hits'], 'total' => $results['totalHits']], $model);
    }

    #[\Override]
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return LazyCollection::make($this->map($builder, $results, $model)->all());
    }

    #[\Override]
    public function getTotalCount($results): int
    {
        return $results['totalHits'];
    }

    #[\Override]
    public function flush($model): void
    {
        $this->memory->flush($model);
    }

    #[\Override]
    public function createIndex($name, array $options = []): mixed
    {
        return null;
    }

    #[\Override]
    public function deleteIndex($name): mixed
    {
        $this->memory->deleteIndex($name);

        return null;
    }

    /** @param  array<string, mixed>  $document */
    private function matches(Builder $builder, array $document): bool
    {
        $text = implode(' ', array_filter($document, is_string(...)));

        if ($builder->query !== '' && stripos($text, $builder->query) === false) {
            return false;
        }

        foreach ($builder->whereIns as $field => $values) {
            $indexed = $document[$field] ?? [];

            if (array_intersect(is_array($indexed) ? $indexed : [$indexed], $values) === []) {
                return false;
            }
        }

        foreach ($builder->wheres as $where) {
            if (($document[$where['field']] ?? null) != $where['value']) {
                return false;
            }
        }

        return true;
    }
}
