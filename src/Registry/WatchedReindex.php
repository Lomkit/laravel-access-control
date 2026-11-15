<?php

namespace Lomkit\Access\Registry;

use Illuminate\Container\Container;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Perimeters\Parents\ParentChain;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final readonly class WatchedReindex
{
    public function __construct(
        private ControlRegistry $registry,
        private ControlMap $discovered,
        private DiscoveryCache $cache,
    ) {}

    /** @throws UnwatchableReindexException When the control reindexes for a model that a fresh process would not watch, or that no searchable document uses. */
    public function verify(string $control, Control $instance): bool
    {
        $freshness = $instance->searchRules()->freshness();

        if (! UsesControlledSearchable::check($instance->getModel())) {
            return $freshness->declared() === [] || $this->inherited($control, $instance);
        }

        $watched = $freshness->watchedModels();

        if ($watched === []) {
            return true;
        }

        if (! in_array($control, $this->discovered->toArray(), true)) {
            throw UnwatchableReindexException::undiscovered($control);
        }

        if ($this->cache->readWatched()?->covers($control, $watched) === false) {
            throw UnwatchableReindexException::staleCache($control);
        }

        return true;
    }

    /** @throws UnwatchableReindexException When no searchable control inherits the keys of the control through a ThroughParent. */
    private function inherited(string $control, Control $instance): bool
    {
        if (! Container::getInstance()->make(ParentChain::class)->isIdle()) {
            return false;
        }

        foreach ($this->discovered->toArray() as $model => $child) {
            if (! WatchMap::watchable($model, $child)) {
                continue;
            }

            $freshness = $this->registry
                ->control($child)
                ->searchRules()
                ->freshness();

            if ($freshness->inheritsReindexOf($instance->getModel())) {
                return true;
            }
        }

        throw UnwatchableReindexException::unsearchable($control, $instance->getModel());
    }
}
