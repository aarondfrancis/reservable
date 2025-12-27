<?php

use AaronFrancis\Reservable\Tests\Models\TestModel;
use Carbon\Carbon;

beforeEach(function () {
    $this->model = TestModel::create(['name' => 'Test']);
});

describe('basic reservations', function () {
    it('can reserve a model', function () {
        $result = $this->model->reserve('processing');

        expect($result)->toBeTrue();
    });

    it('returns false when already reserved', function () {
        $this->model->reserve('processing', 60);

        $result = $this->model->reserve('processing', 60);

        expect($result)->toBeFalse();
    });

    it('can check if a model is reserved', function () {
        expect($this->model->isReserved('processing'))->toBeFalse();

        $this->model->reserve('processing', 60);

        expect($this->model->isReserved('processing'))->toBeTrue();
    });

    it('can release a reservation', function () {
        $this->model->reserve('processing', 60);

        expect($this->model->isReserved('processing'))->toBeTrue();

        $this->model->releaseReservation('processing');

        expect($this->model->isReserved('processing'))->toBeFalse();
    });

    it('allows different keys on same model', function () {
        $this->model->reserve('processing', 60);
        $result = $this->model->reserve('uploading', 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved('processing'))->toBeTrue();
        expect($this->model->isReserved('uploading'))->toBeTrue();
    });

    it('allows same key on different models', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve('processing', 60);
        $result = $model2->reserve('processing', 60);

        expect($result)->toBeTrue();
    });
});

describe('duration handling', function () {
    it('accepts integer seconds', function () {
        $result = $this->model->reserve('processing', 30);

        expect($result)->toBeTrue();
    });

    it('accepts Carbon instance', function () {
        $result = $this->model->reserve('processing', now()->addMinutes(5));

        expect($result)->toBeTrue();
    });

    it('accepts string date', function () {
        $result = $this->model->reserve('processing', '+5 minutes');

        expect($result)->toBeTrue();
    });

    it('expires after duration', function () {
        $this->model->reserve('processing', 1);

        expect($this->model->isReserved('processing'))->toBeTrue();

        Carbon::setTestNow(now()->addSeconds(2));

        expect($this->model->isReserved('processing'))->toBeFalse();
    });
});

describe('key disambiguation', function () {
    it('handles enum keys', function () {
        $enum = TestEnum::Processing;

        $result = $this->model->reserve($enum, 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved($enum))->toBeTrue();
    });

    it('handles object keys', function () {
        $object = new stdClass;

        $result = $this->model->reserve($object, 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved($object))->toBeTrue();
    });

    it('handles string keys', function () {
        $result = $this->model->reserve('my-custom-key', 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved('my-custom-key'))->toBeTrue();
    });
});

describe('query scopes', function () {
    it('filters unreserved models', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve('processing', 60);

        $unreserved = TestModel::unreserved('processing')->get();

        expect($unreserved)->toHaveCount(1);
        expect($unreserved->first()->id)->toBe($model2->id);
    });

    it('filters reserved models', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve('processing', 60);

        $reserved = TestModel::reserved('processing')->get();

        expect($reserved)->toHaveCount(1);
        expect($reserved->first()->id)->toBe($this->model->id);
    });

    it('reserves models via scope', function () {
        TestModel::create(['name' => 'Test 2']);
        TestModel::create(['name' => 'Test 3']);

        $reserved = TestModel::reserveFor('processing', 60)->get();

        // All 3 should be reserved
        expect($reserved)->toHaveCount(3);

        // Trying again should return empty since all are reserved
        $secondTry = TestModel::reserveFor('processing', 60)->get();

        expect($secondTry)->toHaveCount(0);
    });
});

enum TestEnum
{
    case Processing;
    case Uploading;
}
