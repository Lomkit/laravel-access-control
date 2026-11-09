<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Command;
use Lomkit\Access\Access;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'access:cache')]
class AccessCacheCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'access:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and cache the access controls';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        Access::cache();

        $this->components->info('Access controls cached successfully.');
    }
}
