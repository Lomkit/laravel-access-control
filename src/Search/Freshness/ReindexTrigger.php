<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Registry\ConcreteControl;
use Lomkit\Access\Registry\ControlRegistry;

/** @internal */
final readonly class ReindexTrigger
{
    public function __construct(private Container $container) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @throws UnwatchableReindexException When the cached watch map names a class that is no longer a control.
     */
    public function handle(WatchedEvent $event, array $payload): void
    {
        $instance = $payload[0] ?? null;

        if (! $instance instanceof Model) {
            return;
        }

        $registry = $this->container->make(ControlRegistry::class);

        foreach ($registry->controlsWatching($instance) as $control) {
            if (! ConcreteControl::is($control)) {
                throw UnwatchableReindexException::staleCache($control);
            }

            $controlled = $registry->control($control);

            foreach ($controlled->searchRules()->reindexesWatching($instance) as $keys => $reindex) {
                if ($event->triggers($reindex, $instance)) {
                    SearchQueue::push(ReindexWatchedRows::of($control, $keys, $reindex, $instance, $event), new ($controlled->getModel()), $instance->getConnection());
                }
            }
        }
    }
}
