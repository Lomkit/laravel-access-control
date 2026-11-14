<?php

namespace Lomkit\Access;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Lomkit\Access\Console\AccessCacheCommand;
use Lomkit\Access\Console\AccessClearCommand;
use Lomkit\Access\Console\ControlMakeCommand;
use Lomkit\Access\Console\PerimeterMakeCommand;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Registry\PerimeterPurity;
use Lomkit\Access\Search\TrustedSearchEngines;

class AccessServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<Command>> */
    protected array $commands = [
        'AccessCache' => AccessCacheCommand::class,
        'AccessClear' => AccessClearCommand::class,
    ];

    /** @var array<string, class-string<Command>> */
    protected array $devCommands = [
        'ControlMake' => ControlMakeCommand::class,
        'PerimeterMake' => PerimeterMakeCommand::class,
    ];

    /**
     * Registers the service provider.
     */
    #[\Override]
    public function register(): void
    {
        $this->registerCommands($this->commands);

        $this->registerCommands($this->devCommands);

        $this->app->singleton(ControlRegistry::class, fn (Application $app): ControlRegistry => new ControlRegistry(
            $app,
            $app->make(ControlDiscovery::class),
            $app->make(DiscoveryCache::class),
            PerimeterPurity::for($app),
        ));

        $this->app->singleton(ControlDiscovery::class);

        $this->app->singleton(TrustedSearchEngines::class);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerTranslations();

        $this->registerStubs();

        $this->registerOptimizeCommands();

        $this->bootScoutBuilder();
    }

    protected function bootScoutBuilder(): void
    {
        if (class_exists(Builder::class)) {
            Builder::macro('controlled', function (): never {
                throw MissingControlledSearchableException::for($this->model);
            });
        }
    }

    /**
     * Register the given commands.
     *
     * @param  array<string, class-string<Command>>  $commands
     */
    protected function registerCommands(array $commands): void
    {
        foreach ($commands as $commandName => $command) {
            $method = "register{$commandName}Command";

            if (method_exists($this, $method)) {
                $this->{$method}();
            } else {
                $this->app->singleton($command);
            }
        }

        $this->commands(array_values($commands));
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'access-control');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/access-control'),
        ], 'access-control-lang');
    }

    /**
     * Register the stubs on the default laravel stub publish command.
     */
    protected function registerStubs(): void
    {
        Event::listen(function (PublishingStubs $event) {
            $event->add(__DIR__.'/Console/stubs/control.stub', 'control.stub');
            $event->add(__DIR__.'/Console/stubs/perimeter.plain.stub', 'perimeter.plain.stub');
            $event->add(__DIR__.'/Console/stubs/perimeter.hooks.stub', 'perimeter.hooks.stub');
        });
    }

    /**
     * Hook the controls cache into the "optimize" and "optimize:clear" commands.
     */
    protected function registerOptimizeCommands(): void
    {
        $this->optimizes(
            optimize: 'access:cache',
            clear: 'access:clear',
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return list<class-string<Command>>
     */
    #[\Override]
    public function provides(): array
    {
        return [
            ...array_values($this->commands),
            ...array_values($this->devCommands),
        ];
    }
}
