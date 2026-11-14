<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\DuplicateSearchKeyNameException;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Perimeters\ReachedPerimeters;
use Lomkit\Access\Search\Freshness\ReindexWhen;

final class SearchRules
{
    /** @param  array<string, SearchKeys>  $keys */
    private function __construct(
        private readonly array $keys,
        private readonly BoundarySearch $boundary,
    ) {}

    /**
     * @param  class-string  $control
     *
     * @throws DuplicateSearchKeyNameException When two perimeters of the control declare search keys with the same name.
     */
    public static function of(string $control, PerimeterList $perimeters, Boundary $boundary): self
    {
        $keys = [];

        foreach ($perimeters->searchKeys() as $declared) {
            if (array_key_exists($declared->name(), $keys)) {
                throw DuplicateSearchKeyNameException::in($control, $declared->name());
            }

            $keys[$declared->name()] = $declared;
        }

        ksort($keys);

        return new self($keys, BoundarySearch::of($boundary));
    }

    public function isSearchable(): bool
    {
        return $this->boundary->isSearchable();
    }

    public function boundary(): string
    {
        return $this->boundary->boundary();
    }

    /** @return list<string> */
    public function load(): array
    {
        $load = $this->boundary->load();

        foreach ($this->keys as $keys) {
            array_push($load, ...$keys->load());
        }

        return array_values(array_unique($load));
    }

    /** @return list<SearchKeys> */
    public function keys(): array
    {
        return array_values($this->keys);
    }

    public function boundaryKeys(): ?SearchKeys
    {
        return $this->boundary->keys();
    }

    /** @return list<ReindexWhen> */
    public function reindexes(): array
    {
        $reindexes = [];

        foreach ($this->keys as $keys) {
            array_push($reindexes, ...$keys->reindexes()->all());
        }

        return $reindexes;
    }

    /** @return list<class-string<Model>> */
    public function watchedModels(): array
    {
        return array_values(array_unique(array_map(fn (ReindexWhen $reindex): string => $reindex->model(), $this->reindexes())));
    }

    public function reindexFor(string $name, string $watched): ?ReindexWhen
    {
        return ($this->keys[$name] ?? null)?->reindexes()->for($watched);
    }

    /** @return array<string, ReindexWhen> */
    public function reindexesWatching(Model $instance): array
    {
        $reindexes = [];

        foreach ($this->keys as $name => $keys) {
            foreach ($keys->reindexes()->all() as $reindex) {
                if ($reindex->watches($instance)) {
                    $reindexes[$name] = $reindex;
                }
            }
        }

        return $reindexes;
    }

    /** @return array<string, list<string>> */
    public function documentFor(Model $row): array
    {
        $access = [];

        foreach ($this->keys as $keys) {
            array_push($access, ...$keys->rowKeys($row));
        }

        return [
            SearchField::Access->value => array_values(array_unique($access)),
            SearchField::Boundary->value => $this->boundary->rowKeys($row),
        ];
    }

    public function filterFor(ReachedPerimeters $perimeters, AccessContext $context): SearchFilter
    {
        $boundary = $this->boundary->readerKeys($context);

        if ($perimeters->reachesEveryRow()) {
            return SearchFilter::of($boundary, null);
        }

        $access = [];

        foreach ($perimeters->all() as $perimeter) {
            array_push($access, ...($perimeter->declaredSearchKeys()?->readerKeys($context) ?? []));
        }

        return SearchFilter::of($boundary, array_values(array_unique($access)));
    }
}
