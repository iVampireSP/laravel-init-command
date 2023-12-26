<?php

namespace ivampiresp\LaravelInitCommand;

use Illuminate\Support\ServiceProvider;
use ivampiresp\LaravelInitCommand\InitCommand;

class LaravelInitCommandServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InitCommand::class
            ]);
        }
    }

}
