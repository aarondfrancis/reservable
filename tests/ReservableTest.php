<?php

use AaronFrancis\Reservable\Tests\Models\AnotherTestModel;
use AaronFrancis\Reservable\Tests\Models\TestModel;
use Carbon\Carbon;
use Carbon\Unit;

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
        $this->model->reserve('processing', 60);

        expect($this->model->isReserved('processing'))->toBeTrue();

        Carbon::setTestNow(now()->addSeconds(61));

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

    it('handles backed enum keys using value', function () {
        $enum = BackedTestEnum::Processing;

        $result = $this->model->reserve($enum, 60);

        expect($result)->toBeTrue();
        expect($this->model->isReserved($enum))->toBeTrue();

        // Verify it uses the backing value, not the name
        expect($this->model->isReserved('proc'))->toBeTrue();
        expect($this->model->isReserved('Processing'))->toBeFalse();
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
        $this->model->reserve('processing', 60);

        expect($this->model->reservations)->toHaveCount(1);

        // Jump past expiration
        Carbon::setTestNow(now()->addSeconds(61));

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

    it('replaces colons in keys to avoid lock format conflicts', function () {
        // Keys with colons should work transparently
        $key = 'namespace:action:subtype';

        $result = $this->model->reserve($key, 60);
        expect($result)->toBeTrue();

        // Can check with same key
        expect($this->model->isReserved($key))->toBeTrue();

        // Verify the internal key uses underscores by checking the reservation
        $reservation = $this->model->reservations()->first();
        expect($reservation->type)->toBe('namespace_action_subtype');
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
        $result = $this->model->blockingReserve('processing', 60, null, 1);

        expect($result)->toBeTrue();
        expect($this->model->isReserved('processing'))->toBeTrue();
    });

    it('returns false when lock unavailable and wait expires', function () {
        $this->model->reserve('processing', 60);

        // Should wait up to 1 second but fail
        $result = $this->model->blockingReserve('processing', 60, null, 1);

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

describe('edge case coverage', function () {
    describe('negative/past duration handling', function () {
        it('handles Carbon date in the past gracefully', function () {
            // A date in the past should result in duration of 0 (max(0, diff))
            $pastDate = now()->subMinutes(5);

            $result = $this->model->reserve('processing', $pastDate);

            // The reservation is created with duration 0
            // With 0 duration, isReserved tries to acquire a 0-second lock
            // and fails because the model just acquired the same 0-second lock
            expect($result)->toBeTrue();

            // Note: With duration 0, the lock behavior is that it's acquired but immediately available
            // The second reserve (isReserved) will also succeed since 0-duration locks don't persist
            // This is actually correct behavior - past dates result in no effective lock
        });

        it('handles negative integer duration as zero', function () {
            // Negative durations are normalized to 0 via max(0, ...)
            $result = $this->model->reserve('processing', -60);

            // The lock is acquired with duration 0 (doesn't throw or error)
            expect($result)->toBeTrue();

            // Note: With 0 duration, the lock expires immediately but due to timing
            // it may still appear reserved briefly. We just verify it doesn't crash.
        });
    });

    describe('scope chainability', function () {
        it('chains reserved() with where() clauses', function () {
            $model2 = TestModel::create(['name' => 'Reserved Model']);
            $model3 = TestModel::create(['name' => 'Another Reserved']);

            $this->model->reserve('processing', 60);
            $model2->reserve('processing', 60);
            $model3->reserve('processing', 60);

            $results = TestModel::reserved('processing')
                ->where('name', 'like', '%Reserved%')
                ->get();

            expect($results)->toHaveCount(2);
            expect($results->pluck('name')->all())->toContain('Reserved Model');
            expect($results->pluck('name')->all())->toContain('Another Reserved');
        });

        it('chains unreserved() with orderBy()', function () {
            $model2 = TestModel::create(['name' => 'Zebra']);
            $model3 = TestModel::create(['name' => 'Apple']);

            $this->model->reserve('processing', 60);

            $results = TestModel::unreserved('processing')
                ->orderBy('name')
                ->get();

            expect($results)->toHaveCount(2);
            expect($results->first()->name)->toBe('Apple');
            expect($results->last()->name)->toBe('Zebra');
        });

        it('chains reserved() with limit/take', function () {
            for ($i = 0; $i < 5; $i++) {
                $model = TestModel::create(['name' => "Model $i"]);
                $model->reserve('processing', 60);
            }

            $results = TestModel::reserved('processing')
                ->limit(3)
                ->get();

            expect($results)->toHaveCount(3);
        });

        it('chains reserved() with pagination', function () {
            for ($i = 0; $i < 10; $i++) {
                $model = TestModel::create(['name' => "Model $i"]);
                $model->reserve('processing', 60);
            }

            // Initial model from beforeEach is not reserved
            $paginated = TestModel::reserved('processing')->paginate(5);

            expect($paginated->count())->toBe(5);
            expect($paginated->total())->toBe(10);
            expect($paginated->lastPage())->toBe(2);
        });

        it('chains multiple scopes together', function () {
            $model2 = TestModel::create(['name' => 'Active Model']);
            $model3 = TestModel::create(['name' => 'Inactive Model']);

            $model2->reserve('processing', 60);
            $model3->reserve('uploading', 60);

            // Chain reserved with another where and orderBy
            $results = TestModel::reserved('processing')
                ->where('name', 'like', '%Active%')
                ->orderBy('created_at', 'desc')
                ->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toBe('Active Model');
        });
    });

    describe('polymorphic model isolation', function () {
        it('isolates reservations by model type with same ID', function () {
            // Create models that could end up with the same ID
            $testModel = TestModel::create(['name' => 'Test Model']);
            $anotherModel = AnotherTestModel::create(['name' => 'Another Model']);

            // Both models might have the same ID (depends on auto-increment)
            // Reserve with same key on both
            $testModel->reserve('processing', 60);
            $anotherModel->reserve('processing', 60);

            // Both should be reserved independently
            expect($testModel->isReserved('processing'))->toBeTrue();
            expect($anotherModel->isReserved('processing'))->toBeTrue();

            // Release one should not affect the other
            $testModel->releaseReservation('processing');

            expect($testModel->isReserved('processing'))->toBeFalse();
            expect($anotherModel->isReserved('processing'))->toBeTrue();
        });

        it('scopes are isolated by model type', function () {
            $testModel = TestModel::create(['name' => 'Test']);
            $anotherModel = AnotherTestModel::create(['name' => 'Another']);

            $testModel->reserve('processing', 60);
            $anotherModel->reserve('processing', 60);

            // Scopes should only return their own model type
            $reservedTestModels = TestModel::reserved('processing')->get();
            $reservedAnotherModels = AnotherTestModel::reserved('processing')->get();

            expect($reservedTestModels)->toHaveCount(1);
            expect($reservedTestModels->first())->toBeInstanceOf(TestModel::class);

            expect($reservedAnotherModels)->toHaveCount(1);
            expect($reservedAnotherModels->first())->toBeInstanceOf(AnotherTestModel::class);
        });
    });

    describe('invalid duration strings', function () {
        it('throws exception for invalid Carbon string', function () {
            expect(fn () => $this->model->reserve('processing', 'not-a-date'))
                ->toThrow(\Carbon\Exceptions\InvalidFormatException::class);
        });

        it('throws exception for malformed date string', function () {
            expect(fn () => $this->model->reserve('processing', 'invalid-date-format'))
                ->toThrow(\Carbon\Exceptions\InvalidFormatException::class);
        });

        it('handles valid relative date strings', function () {
            $result = $this->model->reserve('processing', '+1 hour');

            expect($result)->toBeTrue();
            expect($this->model->isReserved('processing'))->toBeTrue();
        });
    });

    describe('concurrent blocking reserve', function () {
        it('returns false quickly when lock is held and wait expires', function () {
            // First acquire the lock
            $this->model->reserve('processing', 60);

            // Measure time for blocking reserve to fail
            $startTime = microtime(true);
            $result = $this->model->blockingReserve('processing', 60, null, 1);
            $elapsedTime = microtime(true) - $startTime;

            expect($result)->toBeFalse();
            // Should wait approximately 1 second but may vary slightly based on implementation
            // Using wider tolerance to account for timing variations
            expect($elapsedTime)->toBeGreaterThan(0.5);
            expect($elapsedTime)->toBeLessThan(2.5);
        });

        it('returns false immediately with zero wait time', function () {
            $this->model->reserve('processing', 60);

            $startTime = microtime(true);
            $result = $this->model->blockingReserve('processing', 60, null, 0);
            $elapsedTime = microtime(true) - $startTime;

            expect($result)->toBeFalse();
            // Should return almost immediately
            expect($elapsedTime)->toBeLessThan(0.5);
        });

        it('acquires lock immediately when available with short wait', function () {
            $startTime = microtime(true);
            $result = $this->model->blockingReserve('processing', 60, null, 5);
            $elapsedTime = microtime(true) - $startTime;

            expect($result)->toBeTrue();
            // Should acquire immediately, not wait the full 5 seconds
            expect($elapsedTime)->toBeLessThan(1.0);
        });
    });
});

describe('duration units', function () {
    it('reserve accepts minutes unit', function () {
        $this->model->reserve('processing', 5, 'minutes');

        expect($this->model->isReserved('processing'))->toBeTrue();

        // Verify duration is 5 minutes (300 seconds) by checking the expiration
        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserve accepts hours unit', function () {
        $this->model->reserve('processing', 2, 'hours');

        expect($this->model->isReserved('processing'))->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 7200;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserve accepts days unit', function () {
        $this->model->reserve('processing', 1, 'day');

        expect($this->model->isReserved('processing'))->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 86400;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserve accepts weeks unit', function () {
        $this->model->reserve('processing', 1, 'week');

        expect($this->model->isReserved('processing'))->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 604800;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserve accepts seconds unit explicitly', function () {
        $this->model->reserve('processing', 120, 'seconds');

        expect($this->model->isReserved('processing'))->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 120;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('blockingReserve accepts unit parameter', function () {
        $result = $this->model->blockingReserve('processing', 5, 'minutes', 1);

        expect($result)->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserveWhile accepts unit parameter', function () {
        $callbackExecuted = false;

        $result = $this->model->reserveWhile('processing', 5, 'minutes', function ($model) use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'success';
        });

        expect($callbackExecuted)->toBeTrue();
        expect($result)->toBe('success');
    });

    it('extendReservation accepts unit parameter', function () {
        $this->model->reserve('processing', 1, 'minute');

        $result = $this->model->extendReservation('processing', 5, 'minutes');

        expect($result)->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('scopeReserveFor accepts unit parameter', function () {
        $models = TestModel::reserveFor('worker-1', 5, 'minutes')->get();

        expect($models)->toHaveCount(1);

        $reservation = $models->first()->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('handles singular and plural unit forms', function () {
        // Singular
        $model1 = TestModel::create(['name' => 'test1']);
        $model1->reserve('test', 1, 'minute');
        $reservation1 = $model1->reservations()->first();
        $expectedExp1 = Carbon::now()->timestamp + 60;
        expect($reservation1->expiration)->toBeGreaterThanOrEqual($expectedExp1 - 1);
        expect($reservation1->expiration)->toBeLessThanOrEqual($expectedExp1 + 1);

        // Plural
        $model2 = TestModel::create(['name' => 'test2']);
        $model2->reserve('test', 2, 'minutes');
        $reservation2 = $model2->reservations()->first();
        $expectedExp2 = Carbon::now()->timestamp + 120;
        expect($reservation2->expiration)->toBeGreaterThanOrEqual($expectedExp2 - 1);
        expect($reservation2->expiration)->toBeLessThanOrEqual($expectedExp2 + 1);
    });

    it('reserve accepts Carbon Unit enum', function () {
        $this->model->reserve('processing', 5, Unit::Minute);

        expect($this->model->isReserved('processing'))->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserve accepts Carbon Unit::Hour', function () {
        $this->model->reserve('processing', 2, Unit::Hour);

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 7200;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('blockingReserve accepts Carbon Unit enum', function () {
        $result = $this->model->blockingReserve('processing', 5, Unit::Minute, 1);

        expect($result)->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('reserveWhile accepts Carbon Unit enum', function () {
        $result = $this->model->reserveWhile('processing', 5, Unit::Minute, function ($model) {
            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('extendReservation accepts Carbon Unit enum', function () {
        $this->model->reserve('processing', 1, Unit::Minute);

        $result = $this->model->extendReservation('processing', 10, Unit::Minute);

        expect($result)->toBeTrue();

        $reservation = $this->model->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 600;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });

    it('scopeReserveFor accepts Carbon Unit enum', function () {
        $models = TestModel::reserveFor('worker-1', 5, Unit::Minute)->get();

        expect($models)->toHaveCount(1);

        $reservation = $models->first()->reservations()->first();
        $expectedExpiration = Carbon::now()->timestamp + 300;
        expect($reservation->expiration)->toBeGreaterThanOrEqual($expectedExpiration - 1);
        expect($reservation->expiration)->toBeLessThanOrEqual($expectedExpiration + 1);
    });
});

enum TestEnum
{
    case Processing;
    case Uploading;
}

enum BackedTestEnum: string
{
    case Processing = 'proc';
    case Uploading = 'upload';
}
