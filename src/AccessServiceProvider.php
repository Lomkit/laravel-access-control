<?php

namespace Lomkit\Access;

use Illuminate\Console\Command;
use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Lomkit\Access\Console\AccessCacheCommand;
use Lomkit\Access\Console\AccessClearCommand;
use Lomkit\Access\Console\ControlMakeCommand;
use Lomkit\Access\Console\PerimeterMakeCommand;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Controls\HasControlScope;
use Lomkit\Access\Exceptions\MissingControlException;

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
    public function register(): void
    {
        $this->registerCommands($this->commands);

        $this->registerCommands($this->devCommands);

        $this->mergeConfigFrom(
            __DIR__.'/../config/access-control.php',
            'access-control'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();

        $this->registerStubs();

        $this->registerOptimizeCommands();

        $this->bootScoutBuilder();
    }

    /**
     * Registers a macro on Laravel Scout's Builder.
     */
    protected function bootScoutBuilder(): void
    {
        if (class_exists(Builder::class)) {
            Builder::macro('controlled', function () {
                $model = $this->model;

                /** @var Control $control */
                $control = method_exists($model, 'newControl') ? $model->newControl() : throw MissingControlException::forModel($model::class);

                return $control->scoutQueried($this, HasControlScope::resolveUser());
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

    /**
     * Register the stubs on the default laravel stub publish command.
     */
    protected function registerStubs(): void
    {
        Event::listen(function (PublishingStubs $event) {
            $event->add(__DIR__.'/Console/stubs/control.stub', 'control.stub');
            $event->add(__DIR__.'/Console/stubs/perimeter.plain.stub', 'perimeter.plain.stub');
            $event->add(__DIR__.'/Console/stubs/perimeter.overlay.stub', 'perimeter.overlay.stub');
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
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/access-control.php' => config_path('access-control.php'),
            ], 'access-control-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return list<class-string<Command>>
     */
    public function provides(): array
    {
        return [
            ...array_values($this->commands),
            ...array_values($this->devCommands),
        ];
    }
}
