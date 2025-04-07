<?php

namespace Lomkit\Access;

use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Registers the service provider by merging the access control configuration into the application.
     *
     * This method merges the package's configuration file (located at __DIR__.'/../config/access-control.php')
     * with the application's configuration under the "access-control" key.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/access-control.php',
            'access-control'
        );
    }

    /**
     * Bootstraps package services by registering publishable resources.
     *
     * This method is executed after all service providers have been registered and
     * invokes a routine to make the package configuration file available for publishing
     * when the application is running in a console environment.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPublishing();
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
