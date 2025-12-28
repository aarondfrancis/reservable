<?php

namespace AaronFrancis\Reservable;

use Illuminate\Support\ServiceProvider;

class ReservableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reservable.php', 'reservable');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/reservable.php' => config_path('reservable.php'),
            ], 'reservable-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'reservable-migrations');
        }
    }
}
