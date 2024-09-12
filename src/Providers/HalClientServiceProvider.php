<?php
namespace Amanank\HalClient\Providers;

use Illuminate\Support\ServiceProvider;
use Amanank\HalClient\Client;
use Amanank\HalClient\Console\Commands\GenerateHalModels;

class HalClientServiceProvider extends ServiceProvider {

    /**
     * The path to the configuration file.
     */
    private const CONFIG_PATH = __DIR__ . '/../config/hal-client.php';

    /**
     * The configuration key.
     */
    private const CONFIG_KEY = 'hal-client';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);

        $this->app->singleton(Client::class, function ($app) {
            return new Client($app['config']->get(self::CONFIG_KEY));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        // Optional: Publish the configuration file
        $this->publishes([self::CONFIG_PATH => config_path(self::CONFIG_KEY . '.php')], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateHalModels::class,
            ]);
        }
    }
}
