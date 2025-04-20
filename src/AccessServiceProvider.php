<?php

namespace Lomkit\Access;

use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Lomkit\Access\Console\ControlMakeCommand;
use Lomkit\Access\Console\PerimeterMakeCommand;

class AccessServiceProvider extends ServiceProvider
{
    protected array $devCommands = [
        'ControlMake'   => ControlMakeCommand::class,
        'PerimeterMake' => PerimeterMakeCommand::class,
    ];

    /**
     * Registers the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands($this->devCommands);

        $this->mergeConfigFrom(
            __DIR__.'/../config/access-control.php',
            'access-control'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot()
    {
        $this->registerPublishing();

        $this->registerStubs();

        $this->bootScoutBuilder();
    }

    /**
     * Registers a macro on Laravel Scout's Builder.
     */
    protected function bootScoutBuilder(): void
    {
        if (class_exists(Builder::class)) {
            Builder::macro('controlled', function (Builder $builder) {
                $control = $builder->model->newControl();

                return $control->queried($builder, Auth::user());
            });
        }
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     *
     * @return void
     */
    protected function registerCommands(array $commands)
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
    protected function registerStubs()
    {
        Event::listen(function (PublishingStubs $event) {
            $event->add(realpath(__DIR__.'/Console/stubs/control.stub'), 'control.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/perimeter.plain.stub'), 'perimeter.plain.stub');
            $event->add(realpath(__DIR__.'/Console/stubs/perimeter.overlay.stub'), 'perimeter.overlay.stub');
        });
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
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
     * @return array
     */
    public function provides()
    {
        return array_values($this->devCommands);
    }
}
