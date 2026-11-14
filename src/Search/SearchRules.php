<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\DuplicateSearchKeyNameException;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Perimeters\ReachedPerimeters;

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
