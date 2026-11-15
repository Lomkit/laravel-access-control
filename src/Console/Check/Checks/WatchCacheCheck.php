<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Contracts\Container\Container;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Problems\StaleWatchCache;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\InvalidControlException;
use Lomkit\Access\Registry\WatchMap;
use Throwable;

/** @internal */
final readonly class WatchCacheCheck
{
    public function __construct(
        private Container $container,
        private ControlDiscovery $discovery,
        private DiscoveryCache $cache,
    ) {}

    public function run(Findings $findings, string $path): void
    {
        if (! $this->cache->exists()) {
            return;
        }

        try {
            $cached = $this->cache->readWatched();
        } catch (Throwable) {
            $cached = null;
        }

        if ($cached === null) {
            $findings->problem(new StaleWatchCache($path, missing: true));

            return;
        }

        try {
            $fresh = WatchMap::from($this->discovery->map(), $this->build(...));
        } catch (Throwable) {
            return;
        }

        if ($this->sorted($cached->toArray()) !== $this->sorted($fresh->toArray())) {
            $findings->problem(new StaleWatchCache($path, missing: false));
        }
    }

    /** @param  class-string<Control>  $control */
    private function build(string $control): Control
    {
        $instance = $this->container->make($control);

        if (! $instance instanceof $control) {
            throw InvalidControlException::resolvedAs($control, $instance);
        }

        return $instance;
    }

    /**
     * @param  array<string, list<string>>  $watchers
     * @return array<string, list<string>>
     */
    private function sorted(array $watchers): array
    {
        ksort($watchers);

        return array_map(function (array $controls): array {
            sort($controls);

            return array_values(array_unique($controls));
        }, $watchers);
    }
}
