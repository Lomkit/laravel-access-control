<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Lomkit\Access\Access;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Registry\ControlRegistry;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'access:clear')]
#[Description('Remove the access controls cache file')]
class AccessClearCommand extends Command
{
    public function handle(DiscoveryCache $cache): void
    {
        $cache->forget();

        $this->laravel->forgetInstance(ControlRegistry::class);

        Access::clearResolvedInstance(ControlRegistry::class);

        $this->components->info('Cached access controls cleared successfully.');
    }
}
