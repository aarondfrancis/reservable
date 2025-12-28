<?php

namespace AaronFrancis\Reservable\Tests;

use AaronFrancis\Reservable\ReservableServiceProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    // Use DatabaseMigrations instead of RefreshDatabase.
    // PostgreSQL generated columns have issues with RefreshDatabase's transaction-based approach.
    use DatabaseMigrations;

    protected function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', [
            '--path' => __DIR__.'/database/migrations',
            '--realpath' => true,
        ]);

        $this->beforeApplicationDestroyed(function () {
            $this->artisan('migrate:rollback');
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            ReservableServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', 'testing');

        if ($driver === 'sqlite') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        } elseif ($driver === 'pgsql') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'postgres'),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ]);
        } elseif ($driver === 'mysql') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ]);
        }

        // Use database cache driver
        $app['config']->set('cache.default', 'database');
        $app['config']->set('cache.stores.database', [
            'driver' => 'database',
            'connection' => 'testing',
            'table' => 'cache',
            'lock_connection' => 'testing',
            'lock_table' => 'cache_locks',
        ]);
    }
}
