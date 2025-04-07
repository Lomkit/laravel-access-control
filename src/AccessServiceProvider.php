<?php

namespace Lomkit\Access;

use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Registers the access control package by merging its configuration file with the application's configuration.
     *
     * This method loads configuration settings from the package's access control file and merges them under the
     * 'access-control' key in the application's configuration.
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
     * Bootstraps the Access package by enabling configuration file publishing.
     *
     * Called after all service providers are registered, this method triggers resource publishing,
     * ensuring the access control configuration file is available in the application's configuration
     * directory when running in a console environment.
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
