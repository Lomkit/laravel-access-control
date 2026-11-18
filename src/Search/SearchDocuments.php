<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class SearchDocuments
{
    public function __construct(private SearchRules $rules) {}

    /**
     * @param  list<Model>  $rows
     * @return list<array<string, list<string>>>
     */
    public function metadataFor(array $rows): array
    {
        return array_map(SearchKeyDigest::document(...), $this->keysFor($rows));
    }

    /**
     * @param  list<Model>  $rows
     * @return list<array<string, list<string>>>
     */
    public function keysFor(array $rows): array
    {
        return $this->withoutLazyLoading(function () use ($rows): array {
            $copies = new Collection(array_map($this->copy(...), $rows));
            $load = $this->rules->load();

            if ($load !== []) {
                $copies->load($load);
            }

            $copies->each($this->preventLazyLoading(...));

            return array_values(array_map($this->rules->documentFor(...), $copies->all()));
        });
    }

    private function copy(Model $row): Model
    {
        $copy = $row->withoutRelations();
        $copy->wasRecentlyCreated = false;

        return $copy;
    }

    private function preventLazyLoading(Model $model): void
    {
        $model->preventsLazyLoading = true;

        foreach ($model->getRelations() as $related) {
            foreach ($related instanceof Collection ? $related->all() : [$related] as $relatedModel) {
                if ($relatedModel instanceof Model && ! $relatedModel->preventsLazyLoading) {
                    $this->preventLazyLoading($relatedModel);
                }
            }
        }
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function withoutLazyLoading(Closure $callback): mixed
    {
        $previous = Model::preventsLazyLoading();

        Model::preventLazyLoading();

        try {
            return $callback();
        } finally {
            Model::preventLazyLoading($previous);
        }
    }
}
