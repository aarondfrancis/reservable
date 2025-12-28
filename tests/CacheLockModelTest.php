<?php

use AaronFrancis\Reservable\CacheLock;

describe('model configuration', function () {
    it('has table name cache_locks', function () {
        $model = new CacheLock;

        expect($model->getTable())->toBe('cache_locks');
    });

    it('has primary key set to key', function () {
        $model = new CacheLock;

        expect($model->getKeyName())->toBe('key');
    });

    it('has non-incrementing primary key', function () {
        $model = new CacheLock;

        expect($model->getIncrementing())->toBeFalse();
    });

    it('has timestamps disabled', function () {
        $model = new CacheLock;

        expect($model->usesTimestamps())->toBeFalse();
    });

    it('uses string key type', function () {
        $model = new CacheLock;

        expect($model->getKeyType())->toBe('string');
    });
});

describe('model instantiation', function () {
    it('can be instantiated with attributes', function () {
        $model = new CacheLock([
            'key' => 'test-lock-key',
            'owner' => 'test-owner',
            'expiration' => time() + 60,
        ]);

        expect($model->key)->toBe('test-lock-key');
        expect($model->owner)->toBe('test-owner');
        expect($model->expiration)->toBe(time() + 60);
    });

    it('can be instantiated without attributes', function () {
        $model = new CacheLock;

        expect($model)->toBeInstanceOf(CacheLock::class);
        expect($model->key)->toBeNull();
    });

    it('allows mass assignment of attributes', function () {
        $model = new CacheLock;

        $model->fill([
            'key' => 'another-key',
            'owner' => 'another-owner',
            'expiration' => 12345,
        ]);

        expect($model->key)->toBe('another-key');
        expect($model->owner)->toBe('another-owner');
        expect($model->expiration)->toBe(12345);
    });
});
