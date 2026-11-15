<?php

namespace Lomkit\Access\Search\Parents;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\UnsearchableThroughParentException;
use Lomkit\Access\Search\Freshness\ReindexRule;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\SearchRules;

/** @internal */
final readonly class ParentSearch
{
    private ParentKey $key;

    public function __construct(
        private string $relation,
        private ?string $inverse,
        private Control $parent,
        private ?Ability $view,
    ) {
        $this->key = ParentKey::through($relation);
    }

    /** @throws UnsearchableThroughParentException When the documents of the child could not follow the keys of the parent exactly. */
    public function ensureSearchable(string $control): void
    {
        if ($this->view === null) {
            return;
        }

        if ($this->inverse === null) {
            throw UnsearchableThroughParentException::missingInverse($control, $this->relation, $this->parent->getModel());
        }

        if (! $this->view->is(Ability::view())) {
            throw UnsearchableThroughParentException::mappedView($control, $this->relation, $this->view);
        }

        if (! $this->rules()->isSearchable()) {
            throw UnsearchableThroughParentException::unsearchableBoundary($control, $this->relation, $this->parent::class);
        }

        foreach ($this->rules()->parents() as $grandparent) {
            $grandparent->ensureSearchable($this->parent::class);
        }
    }

    public function name(): string
    {
        return $this->key->name();
    }

    /** @return list<string> */
    public function load(): array
    {
        if ($this->view === null) {
            return [];
        }

        return [$this->relation, ...array_map(fn (string $path): string => "{$this->relation}.{$path}", $this->rules()->load())];
    }

    /** @return list<string> */
    public function rowKeys(Model $row): array
    {
        $parent = $this->view !== null && $row->relationLoaded($this->relation) ? $row->getRelation($this->relation) : null;

        if (! $parent instanceof Model) {
            return [];
        }

        $document = $this->rules()->documentFor($parent);
        $boundary = $this->rules()->isBounded() ? $document[SearchField::Boundary->value] : null;

        return [...$this->key->products($boundary, null), ...$this->key->products($boundary, $document[SearchField::Access->value])];
    }

    /** @return list<string> */
    public function readerKeys(AccessContext $context): array
    {
        if ($this->view === null) {
            return [];
        }

        $filter = $this->parent->searchFilterIn($context->withAbility($this->view));

        return $filter->isClosed() ? [] : $this->key->products($filter->boundaryKeys(), $filter->accessKeys());
    }

    /** @return array<string, list<ReindexRule>> */
    public function freshness(): array
    {
        $inverse = $this->inverse;

        if ($this->view === null || $inverse === null) {
            return [];
        }

        $rules = [$this->name() => [new ParentReindex($this->parent, $inverse)]];

        foreach ($this->rules()->freshness()->dependencies() as $name => $dependencies) {
            $rules["{$this->name()}.{$name}"] = array_map(
                fn (ReindexRule $dependency): ReindexRule => new ChildrenReindex($dependency, $name, $this->parent->getModel(), $inverse),
                $dependencies,
            );
        }

        return $rules;
    }

    private function rules(): SearchRules
    {
        return $this->parent->searchRules();
    }
}
