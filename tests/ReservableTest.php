<?php

use AaronFrancis\Reservable\Tests\Models\TestModel;
use Carbon\Carbon;

beforeEach(function () {
    $this->model = TestModel::create(['name' => 'Test']);
});

afterEach(function () {
    Carbon::setTestNow();
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

describe('reservations relationship', function () {
    it('returns active reservations', function () {
        $this->model->reserve('processing', 60);
        $this->model->reserve('uploading', 60);

        $reservations = $this->model->reservations;

        expect($reservations)->toHaveCount(2);
        expect($reservations->pluck('type')->sort()->values()->all())->toBe(['processing', 'uploading']);
    });

    it('excludes expired reservations', function () {
        $this->model->reserve('processing', 1);

        expect($this->model->reservations)->toHaveCount(1);

        Carbon::setTestNow(now()->addSeconds(2));

        // Refresh to get fresh relationship
        $this->model->refresh();

        expect($this->model->reservations)->toHaveCount(0);
    });

    it('only includes reservations for this model', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve('processing', 60);
        $model2->reserve('processing', 60);

        expect($this->model->reservations)->toHaveCount(1);
        expect($model2->reservations)->toHaveCount(1);
    });
});

describe('edge cases', function () {
    it('releasing non-existent reservation does not throw', function () {
        // Should not throw
        $this->model->releaseReservation('never-reserved');

        expect(true)->toBeTrue();
    });

    it('can re-reserve after release', function () {
        $this->model->reserve('processing', 60);
        $this->model->releaseReservation('processing');

        $result = $this->model->reserve('processing', 60);

        expect($result)->toBeTrue();
    });

    it('can re-reserve after expiration', function () {
        $this->model->reserve('processing', 1);

        Carbon::setTestNow(now()->addSeconds(2));

        $result = $this->model->reserve('processing', 60);

        expect($result)->toBeTrue();
    });

    it('zero duration reservation fails immediately', function () {
        // First reservation should succeed
        $this->model->reserve('processing', 60);

        // Zero duration means "check if reserved" - should fail
        $result = $this->model->reserve('processing', 0);

        expect($result)->toBeFalse();
    });

    it('uses default duration of 60 seconds', function () {
        $this->model->reserve('processing');

        expect($this->model->isReserved('processing'))->toBeTrue();

        // Still reserved after 59 seconds
        Carbon::setTestNow(now()->addSeconds(59));
        expect($this->model->isReserved('processing'))->toBeTrue();

        // Expired after 61 seconds
        Carbon::setTestNow(now()->addSeconds(2));
        expect($this->model->isReserved('processing'))->toBeFalse();
    });

    it('handles special characters in keys', function () {
        $key = 'user:123:action/process@task#1';

        $result = $this->model->reserve($key, 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved($key))->toBeTrue();
    });

    it('handles numeric keys', function () {
        $result = $this->model->reserve(12345, 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved(12345))->toBeTrue();
    });

    it('handles empty string key', function () {
        $result = $this->model->reserve('', 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved(''))->toBeTrue();
    });
});

describe('scope key isolation', function () {
    it('reserved scope is key-specific', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve('processing', 60);
        $model2->reserve('uploading', 60);

        // Only model1 is reserved for 'processing'
        $reserved = TestModel::reserved('processing')->get();
        expect($reserved)->toHaveCount(1);
        expect($reserved->first()->id)->toBe($this->model->id);

        // Only model2 is reserved for 'uploading'
        $reserved = TestModel::reserved('uploading')->get();
        expect($reserved)->toHaveCount(1);
        expect($reserved->first()->id)->toBe($model2->id);
    });

    it('unreserved scope is key-specific', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve('processing', 60);

        // model2 is unreserved for 'processing'
        $unreserved = TestModel::unreserved('processing')->get();
        expect($unreserved)->toHaveCount(1);
        expect($unreserved->first()->id)->toBe($model2->id);

        // Both are unreserved for 'uploading'
        $unreserved = TestModel::unreserved('uploading')->get();
        expect($unreserved)->toHaveCount(2);
    });

    it('scopes work with enum keys', function () {
        $model2 = TestModel::create(['name' => 'Test 2']);

        $this->model->reserve(TestEnum::Processing, 60);

        $reserved = TestModel::reserved(TestEnum::Processing)->get();
        expect($reserved)->toHaveCount(1);
        expect($reserved->first()->id)->toBe($this->model->id);

        $unreserved = TestModel::unreserved(TestEnum::Processing)->get();
        expect($unreserved)->toHaveCount(1);
        expect($unreserved->first()->id)->toBe($model2->id);
    });

    it('reserveFor scope uses enum keys', function () {
        TestModel::create(['name' => 'Test 2']);

        $reserved = TestModel::reserveFor(TestEnum::Uploading, 60)->get();

        expect($reserved)->toHaveCount(2);
        expect($this->model->isReserved(TestEnum::Uploading))->toBeTrue();
    });
});

describe('reserveWhile callback', function () {
    it('executes callback when lock acquired', function () {
        $result = $this->model->reserveWhile('processing', 60, function ($model) {
            return 'completed';
        });

        expect($result)->toBe('completed');
    });

    it('passes the model to callback', function () {
        $result = $this->model->reserveWhile('processing', 60, function ($model) {
            return $model->id;
        });

        expect($result)->toBe($this->model->id);
    });

    it('releases lock after callback completes', function () {
        $this->model->reserveWhile('processing', 60, function ($model) {
            expect($model->isReserved('processing'))->toBeTrue();
        });

        expect($this->model->isReserved('processing'))->toBeFalse();
    });

    it('releases lock even if callback throws', function () {
        try {
            $this->model->reserveWhile('processing', 60, function ($model) {
                throw new \Exception('Test exception');
            });
        } catch (\Exception $e) {
            // Expected
        }

        expect($this->model->isReserved('processing'))->toBeFalse();
    });

    it('returns false when lock cannot be acquired', function () {
        $this->model->reserve('processing', 60);

        $result = $this->model->reserveWhile('processing', 60, function ($model) {
            return 'should not execute';
        });

        expect($result)->toBeFalse();
    });
});

describe('blockingReserve', function () {
    it('acquires lock immediately when available', function () {
        $result = $this->model->blockingReserve('processing', 60, 1);

        expect($result)->toBeTrue();
        expect($this->model->isReserved('processing'))->toBeTrue();
    });

    it('returns false when lock unavailable and wait expires', function () {
        $this->model->reserve('processing', 60);

        // Should wait up to 1 second but fail
        $result = $this->model->blockingReserve('processing', 60, 1);

        expect($result)->toBeFalse();
    });

    it('uses default wait time of 10 seconds', function () {
        // Just verify it accepts the call with default wait
        $result = $this->model->blockingReserve('processing', 60);

        expect($result)->toBeTrue();
    });
});

describe('extendReservation', function () {
    it('extends an active reservation', function () {
        $this->model->reserve('processing', 10);

        expect($this->model->isReserved('processing'))->toBeTrue();

        // Extend by 60 more seconds
        $result = $this->model->extendReservation('processing', 60);

        expect($result)->toBeTrue();

        // Should still be reserved after original expiration
        Carbon::setTestNow(now()->addSeconds(15));
        expect($this->model->isReserved('processing'))->toBeTrue();
    });

    it('returns false when no active reservation exists', function () {
        $result = $this->model->extendReservation('processing', 60);

        expect($result)->toBeFalse();
    });

    it('returns false when reservation has expired', function () {
        $this->model->reserve('processing', 1);

        Carbon::setTestNow(now()->addSeconds(2));

        $result = $this->model->extendReservation('processing', 60);

        expect($result)->toBeFalse();
    });

    it('accepts Carbon duration', function () {
        $this->model->reserve('processing', 10);

        $result = $this->model->extendReservation('processing', now()->addMinutes(5));

        expect($result)->toBeTrue();
    });

    it('accepts string duration', function () {
        $this->model->reserve('processing', 10);

        $result = $this->model->extendReservation('processing', '+5 minutes');

        expect($result)->toBeTrue();
    });
});

enum TestEnum
{
    case Processing;
    case Uploading;
}
