<?php

namespace JohannesSchobel\DingoQueryMapper;

use Illuminate\Support\ServiceProvider;

class DingoQueryMapperServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/dingoquerymapper.php'   => config_path('dingoquerymapper.php'),
        ], 'config');
    }

    public function register()
    {
        $this->setupConfig();
    }

    /**
     * Get the Configuration
     */
    private function setupConfig()
    {
        $this->mergeConfigFrom(realpath(__DIR__ . '/../../config/dingoquerymapper.php'), 'dingoquerymapper');
    }
}
