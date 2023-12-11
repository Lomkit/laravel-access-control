<?php

namespace Lomkit\Access;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Lomkit\Access\Perimeters\Perimeters;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/access-control.php',
            'access-control'
        );

        $this->registerServices();
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPublishing();

        $this->registerPerimeters();

        $this->registerMacros();
    }

    /**
     * Register Access's macros.
     *
     * @return void
     */
    protected function registerMacros()
    {
        // @TODO: or bind via trait ? In order to link for control ?
        Builder::macro(
            'control',
            function () {
                dd($this);
            }
        );
    }

    /**
     * Register Access's perimeters.
     *
     * @return void
     */
    protected function registerPerimeters()
    {
        $this->app->make(Perimeters::class)
            ->perimetersIn(
                config('access-control.perimeters.path', app_path('Access/Perimeters'))
            );
    }

    /**
     * Register Access's services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->singleton(Perimeters::class);
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
}
