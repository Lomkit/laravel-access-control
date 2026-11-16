<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Providers;

use Illuminate\Support\ServiceProvider;
use Lomkit\Access\Access;

class MemosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Access::discoverControlsIn([dirname(__DIR__).'/Module']);
    }
}
