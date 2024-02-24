<?php

namespace Lomkit\Access;

use Illuminate\Support\ServiceProvider;
use Lomkit\Access\Perimeters\Perimeters;

class AccessServiceProvider extends ServiceProvider
{
    // @TODO: add the ability to remove control scope on certain conditions

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
