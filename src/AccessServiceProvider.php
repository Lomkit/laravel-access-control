<?php

namespace Lomkit\Access;

use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Registers the service provider by merging the default access control configuration.
     *
     * Merges the package's configuration file with the application's configuration under the key
     * "access-control" to ensure that default access control settings are applied.
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
     * Boot the access control package.
     *
     * This method is executed after all service providers have been registered. It registers publishing
     * resources, ensuring that the access control configuration file is available in the application's
     * configuration directory when running in the console.
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
