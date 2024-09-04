<?php

namespace Amanank\HalClient\Providers;

use Illuminate\Support\ServiceProvider;
use Amanank\HalClient\Client;
use Amanank\HalClient\Console\Commands\GenerateHalModels;

class HalClientServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Client::class, function ($app) {
            $config = require __DIR__ . '/../../config/hal-client.php';
            return new Client($config);
        });
    }

    public function boot()
    {
        // Optional: Publish the configuration file
        $this->publishes([
            __DIR__ . '/../../config/hal-client.php' => config_path('hal-client.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateHalModels::class,
            ]);
        }
    }
}