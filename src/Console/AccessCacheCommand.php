<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'access:cache')]
#[Description('Discover the access controls and cache the control of each model')]
class AccessCacheCommand extends Command
{
    public function handle(ControlDiscovery $discovery, DiscoveryCache $cache): void
    {
        $cache->write($discovery->map());

        $this->components->info('Access controls cached successfully.');
    }
}
