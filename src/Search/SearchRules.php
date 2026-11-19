<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\DuplicateSearchKeyNameException;
use Lomkit\Access\Exceptions\UnsearchableThroughParentException;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Perimeters\ReachedPerimeters;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Search\Freshness\FreshnessRules;
use Lomkit\Access\Search\Parents\ParentKey;
use Lomkit\Access\Search\Parents\ParentSearch;

/** @internal */
final class SearchRules
{
    /**
     * @param  array<string, SearchKeys>  $keys
     * @param  list<ParentSearch>  $parents
     */
    private function __construct(
        private readonly array $keys,
        private readonly BoundarySearch $boundary,
        private readonly array $parents,
        private readonly FreshnessRules $freshness,
    ) {}

    /**
     * @param  class-string  $control
     * @param  class-string<Model>  $model
     *
     * @throws DuplicateSearchKeyNameException When two perimeters of the control declare search keys with the same name.
     * @throws UnsearchableThroughParentException When the model is searchable and a ThroughParent cannot be searched exactly.
     */
    public static function of(string $control, string $model, PerimeterList $perimeters, Boundary $boundary): self
    {
        $keys = [];
        $parents = array_map(fn (ThroughParent $parent): ParentSearch => $parent->parentSearch(), $perimeters->parents());

        foreach ($perimeters->searchKeys() as $declared) {
            if (array_key_exists($declared->name(), $keys)) {
                throw DuplicateSearchKeyNameException::in($control, $declared->name());
            }

            if ($parents !== [] && $declared->name() === ParentKey::PREFIX) {
                throw DuplicateSearchKeyNameException::reservedForParents($control, $declared->name());
            }

            $keys[$declared->name()] = $declared;
        }

        ksort($keys);

        return new self($keys, BoundarySearch::of($boundary), $parents, FreshnessRules::of($control, $keys, $parents, UsesControlledSearchable::check($model)));
    }

    public function isSearchable(): bool
    {
        return $this->boundary->isSearchable();
    }

    public function boundary(): string
    {
        return $this->boundary->boundary();
    }

    public function isBounded(): bool
    {
        return $this->boundary->isBounded();
    }

    /** @return list<ParentSearch> */
    public function parents(): array
    {
        return $this->parents;
    }

    public function freshness(): FreshnessRules
    {
        return $this->freshness;
    }

    /** @return list<string> */
    public function load(): array
    {
        $load = $this->boundary->load();

        foreach ($this->keys as $keys) {
            array_push($load, ...$keys->load());
        }

        foreach ($this->parents as $parent) {
            array_push($load, ...$parent->load());
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

    /** @return array<string, list<string>> */
    public function documentFor(Model $row): array
    {
        $access = [];

        foreach ($this->keys as $keys) {
            array_push($access, ...$keys->rowKeys($row));
        }

        foreach ($this->parents as $parent) {
            array_push($access, ...$parent->rowKeys($row));
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
            $keys = $perimeter instanceof ThroughParent
                ? $perimeter->decision()->readerKeys($context)
                : $perimeter->declaredSearchKeys()?->readerKeys($context) ?? [];

            array_push($access, ...$keys);
        }

        return SearchFilter::of($boundary, array_values(array_unique($access)));
    }
}
