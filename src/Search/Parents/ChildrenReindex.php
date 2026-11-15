<?php

namespace Lomkit\Access\Search\Parents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Search\Freshness\ReindexRule;
use Lomkit\Access\Search\Freshness\ReindexWhen;
use Lomkit\Access\Search\Freshness\WatchedEvent;

/** @internal */
final readonly class ChildrenReindex implements ReindexRule
{
    /** @param  class-string<Model>  $parent */
    public function __construct(
        private ReindexRule $parentRule,
        private string $parentKeys,
        private string $parent,
        private string $inverse,
    ) {}

    /** @param  class-string<Model>  $parent */
    public function inheritsReindexOf(string $parent): bool
    {
        return $this->parentRule instanceof self
            ? $this->parentRule->inheritsReindexOf($parent)
            : $this->parentRule instanceof ReindexWhen && $this->parent === $parent;
    }

    #[\Override]
    public function model(): string
    {
        return $this->parentRule->model();
    }

    #[\Override]
    public function watches(Model $instance): bool
    {
        return $this->parentRule->watches($instance);
    }

    #[\Override]
    public function isTriggeredBy(WatchedEvent $event, Model $instance): bool
    {
        return $this->parentRule->isTriggeredBy($event, $instance);
    }

    #[\Override]
    public function previousValues(Model $instance): array
    {
        return $this->parentRule->previousValues($instance);
    }

    #[\Override]
    public function rowsFor(Model $instance, string $keys, string $controlled): Builder
    {
        $parents = $this->parentRule->rowsFor($instance, $this->parentKeys, $this->parent);
        $inverse = InverseRelation::of(new $this->parent, $this->inverse);
        $children = $inverse
            ->getRelated()
            ->newQueryWithoutScopes()
            ->whereIn($inverse->getQualifiedForeignKeyName(), $parents->withoutGlobalScopes()->select($inverse->getQualifiedParentKeyName()));

        if (! $children->getModel() instanceof $controlled) {
            throw InvalidReindexRowsException::anotherModel($keys, $controlled, $children->getModel()::class);
        }

        return $children;
    }
}
