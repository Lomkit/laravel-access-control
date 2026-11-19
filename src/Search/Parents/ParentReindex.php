<?php

namespace Lomkit\Access\Search\Parents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Search\Freshness\ModelSnapshot;
use Lomkit\Access\Search\Freshness\ReindexRule;
use Lomkit\Access\Search\Freshness\WatchedEvent;
use Lomkit\Access\Search\SearchDocuments;

/** @internal */
final readonly class ParentReindex implements ReindexRule
{
    public function __construct(
        private Control $parent,
        private string $inverse,
    ) {}

    #[\Override]
    public function model(): string
    {
        return $this->parent->getModel();
    }

    #[\Override]
    public function watches(Model $instance): bool
    {
        return $instance instanceof ($this->parent->getModel());
    }

    #[\Override]
    public function isTriggeredBy(WatchedEvent $event, Model $instance): bool
    {
        return match ($event) {
            WatchedEvent::Created => false,
            WatchedEvent::Deleted, WatchedEvent::Restored => true,
            WatchedEvent::Updated => $this->keysChanged($instance),
        };
    }

    #[\Override]
    public function previousValues(Model $instance): array
    {
        return array_intersect_key($instance->getPrevious(), array_flip([InverseRelation::of($instance, $this->inverse)->getLocalKeyName()]));
    }

    #[\Override]
    public function rowsFor(Model $instance, string $keys, string $controlled): Builder
    {
        $children = InverseRelation::of($instance, $this->inverse)->getQuery();

        if (! $children->getModel() instanceof $controlled) {
            throw InvalidReindexRowsException::anotherModel($keys, $controlled, $children->getModel()::class);
        }

        return $children;
    }

    private function keysChanged(Model $instance): bool
    {
        $documents = (new SearchDocuments($this->parent->searchRules()))->keysFor([
            $instance->newFromBuilder(ModelSnapshot::previous($instance)),
            $instance->newFromBuilder(ModelSnapshot::current($instance)),
        ]);

        return $documents[0] !== $documents[1];
    }
}
