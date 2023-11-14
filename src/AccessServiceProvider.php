<?php

namespace Lomkit\Access;

use Illuminate\Support\ServiceProvider;
class AccessServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerServices();
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register Rest's services in the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        // $this->app->singleton('lomkit-access', Access::class);
    }
}
