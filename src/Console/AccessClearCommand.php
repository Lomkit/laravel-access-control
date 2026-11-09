<?php

namespace Lomkit\Access\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Lomkit\Access\Access;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'access:clear')]
class AccessClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'access:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the access controls cache file';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): void
    {
        $files->delete(Access::cachePath());

        Access::flush();

        $this->components->info('Cached access controls cleared successfully.');
    }
}
