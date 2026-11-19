<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Lomkit\Access\Access;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Registry\WatchMap;
use Symfony\Component\Console\Attribute\AsCommand;

/** @internal */
#[AsCommand(name: 'access:cache')]
#[Description('Discover the access controls and cache the control of each model')]
class AccessCacheCommand extends Command
{
    public function handle(ControlDiscovery $discovery, DiscoveryCache $cache): void
    {
        $cache->forget();
        $this->laravel->forgetInstance(ControlRegistry::class);
        Access::clearResolvedInstance(ControlRegistry::class);

        $controls = $discovery->map();
        $watched = WatchMap::of($this->laravel->make(ControlRegistry::class), $controls);

        $cache->write($controls);
        $cache->writeWatched($watched);

        $this->components->info('Access controls cached successfully.');
    }
}
