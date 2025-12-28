<?php

use AaronFrancis\Reservable\ReservableServiceProvider;

describe('config handling', function () {
    it('merges config correctly', function () {
        $config = config('reservable');

        expect($config)->toBeArray();
        expect($config)->toHaveKey('model');
        expect($config['model'])->toBe(\AaronFrancis\Reservable\Models\CacheLock::class);
    });

    it('has correct default model class', function () {
        $modelClass = config('reservable.model');

        expect($modelClass)->toBe(\AaronFrancis\Reservable\Models\CacheLock::class);
        expect(class_exists($modelClass))->toBeTrue();
    });
});

describe('publishables', function () {
    it('config is publishable with tag reservable-config', function () {
        $publishGroups = ReservableServiceProvider::$publishGroups['reservable-config'] ?? [];

        // Check that config is in publish groups
        expect($publishGroups)->not->toBeEmpty();

        // Verify the config file path is included (uses relative path from __DIR__)
        $sourcePaths = array_keys($publishGroups);
        $hasConfigFile = collect($sourcePaths)->contains(function ($path) {
            return str_contains($path, 'config/reservable.php');
        });

        expect($hasConfigFile)->toBeTrue();
    });

    it('migrations are publishable with tag reservable-migrations', function () {
        $publishGroups = ReservableServiceProvider::$publishGroups['reservable-migrations'] ?? [];

        expect($publishGroups)->not->toBeEmpty();

        // Verify migrations directory is included
        $sourcePaths = array_keys($publishGroups);
        $hasMigrations = collect($sourcePaths)->contains(function ($path) {
            return str_contains($path, 'database/migrations');
        });

        expect($hasMigrations)->toBeTrue();
    });

    it('publishes only runs when runningInConsole', function () {
        // The service provider wraps publishes in runningInConsole check
        // We verify this by checking the provider source has the conditional
        $providerPath = __DIR__.'/../src/ReservableServiceProvider.php';
        $providerSource = file_get_contents($providerPath);

        expect($providerSource)->toContain('runningInConsole()');
        expect($providerSource)->toContain('$this->publishes(');
        expect($providerSource)->toContain('$this->publishesMigrations(');
    });
});
