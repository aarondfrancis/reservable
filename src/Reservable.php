<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Reservable;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\Unit;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Provides reservation (locking) functionality for Eloquent models.
 *
 * This trait enables models to be temporarily "reserved" using cache-based locks,
 * preventing concurrent access or processing. Reservations are time-limited and
 * automatically expire, making them ideal for distributed systems where you need
 * to ensure only one process handles a specific model at a time.
 *
 * @example
 * // Reserve a model for 60 seconds
 * if ($job->reserve('processing')) {
 *     // Process the job...
 *     $job->releaseReservation('processing');
 * }
 * @example
 * // Use blocking reservation to wait for availability
 * if ($job->blockingReserve('processing', 120, 30)) {
 *     // Got the lock, process the job...
 * }
 * @example
 * // Query only unreserved models
 * $availableJobs = Job::unreserved('processing')->limit(10)->get();
 */
trait Reservable
{
    /**
     * Default duration in seconds for a reservation when none is specified.
     */
    private const DEFAULT_RESERVATION_DURATION = 60;

    /**
     * Default time in seconds to wait when attempting a blocking reservation.
     */
    private const DEFAULT_BLOCKING_WAIT_TIME = 10;

    /**
     * Attempt to acquire a non-blocking reservation on this model.
     *
     * This method tries to obtain a lock immediately and returns the result.
     * If the lock is already held by another process, it returns false without waiting.
     *
     * @param  mixed  $key  The reservation key (string, enum, or object). Objects use their class name.
     * @param  string|int|Carbon  $duration  Lock duration in seconds, or a Carbon instance/parseable string for absolute time.
     * @param  Unit|string|null  $unit  Optional time unit (Unit::Minute, 'minutes', etc.) when $duration is an integer.
     * @return bool True if the reservation was acquired, false if already reserved.
     *
     * @example
     * // Reserve for 60 seconds (default)
     * $model->reserve('processing');
     * @example
     * // Reserve for 5 minutes using seconds
     * $model->reserve('processing', 300);
     * @example
     * // Reserve for 5 minutes using Carbon Unit enum
     * $model->reserve('processing', 5, Unit::Minute);
     * @example
     * // Reserve for 2 hours using string
     * $model->reserve('processing', 2, 'hours');
     * @example
     * // Reserve until a specific time
     * $model->reserve('processing', now()->addHour());
     * @example
     * // Reserve using an enum key
     * $model->reserve(ReservationType::Processing);
     */
    public function reserve(mixed $key, string|int|Carbon $duration = self::DEFAULT_RESERVATION_DURATION, Unit|string|null $unit = null): bool
    {
        return $this->reservation($key, $duration, $unit)->get();
    }

    /**
     * Attempt to acquire a blocking reservation, waiting if necessary.
     *
     * This method will wait up to the specified time for the lock to become available.
     * If the lock cannot be acquired within the wait time, it returns false.
     *
     * @param  mixed  $key  The reservation key (string, enum, or object). Objects use their class name.
     * @param  string|int|Carbon  $duration  Lock duration in seconds, or a Carbon instance/parseable string for absolute time.
     * @param  Unit|string|null  $unit  Optional time unit (Unit::Minute, 'minutes', etc.) when $duration is an integer.
     * @param  int  $wait  Maximum seconds to wait for the lock to become available.
     * @return bool True if the reservation was acquired, false if timeout occurred.
     *
     * @example
     * // Wait up to 10 seconds for a 60-second reservation
     * if ($model->blockingReserve('processing')) {
     *     // Process...
     * }
     * @example
     * // Wait up to 30 seconds for a 5-minute reservation using Unit enum
     * if ($model->blockingReserve('processing', 5, Unit::Minute, 30)) {
     *     // Process...
     * }
     * @example
     * // Wait up to 30 seconds for a 2-hour reservation using string
     * if ($model->blockingReserve('processing', 2, 'hours', 30)) {
     *     // Process...
     * }
     */
    public function blockingReserve(mixed $key, string|int|Carbon $duration = self::DEFAULT_RESERVATION_DURATION, Unit|string|null $unit = null, int $wait = self::DEFAULT_BLOCKING_WAIT_TIME): bool
    {
        try {
            return $this->reservation($key, $duration, $unit)->block($wait);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return false;
        }
    }

    /**
     * Acquire a reservation and execute a callback while holding it.
     *
     * The reservation is automatically released after the callback completes,
     * regardless of whether it succeeds or throws an exception.
     *
     * @param  mixed  $key  The reservation key (string, enum, or object). Objects use their class name.
     * @param  string|int|Carbon  $duration  Lock duration in seconds, or a Carbon instance/parseable string for absolute time.
     * @param  callable|Unit|string|null  $callbackOrUnit  The callback to execute, or a time unit (Unit::Minute, 'minutes') if using the 4-argument form.
     * @param  callable|null  $callback  The callback when using the 4-argument form with a unit.
     * @return mixed The return value of the callback, or false if the lock couldn't be acquired.
     *
     * @example
     * $result = $model->reserveWhile('processing', 120, function ($model) {
     *     // Do work with the model...
     *     return $model->process();
     * });
     * @example
     * $result = $model->reserveWhile('processing', 5, Unit::Minute, function ($model) {
     *     return $model->process();
     * });
     */
    public function reserveWhile(mixed $key, string|int|Carbon $duration, callable|Unit|string|null $callbackOrUnit = null, ?callable $callback = null): mixed
    {
        // Handle both signatures: (key, duration, callback) and (key, duration, unit, callback)
        if (is_callable($callbackOrUnit)) {
            $unit = null;
            $callback = $callbackOrUnit;
        } else {
            $unit = $callbackOrUnit;
        }

        return $this->reservation($key, $duration, $unit)->get(function () use ($callback) {
            return $callback($this);
        });
    }

    /**
     * Extend the duration of an existing reservation.
     *
     * This method updates the expiration time of an active reservation. It only
     * succeeds if the reservation exists and hasn't expired. Useful for long-running
     * operations that may exceed the original reservation duration.
     *
     * @param  mixed  $key  The reservation key to extend.
     * @param  string|int|Carbon  $duration  Additional duration in seconds, or a Carbon instance/parseable string for absolute time.
     * @param  Unit|string|null  $unit  Optional time unit (Unit::Minute, 'minutes', etc.) when $duration is an integer.
     * @return bool True if the reservation was extended, false if no active reservation exists.
     *
     * @example
     * // Extend by another 60 seconds
     * $model->extendReservation('processing');
     * @example
     * // Extend by 5 minutes using seconds
     * $model->extendReservation('processing', 300);
     * @example
     * // Extend by 5 minutes using Unit enum
     * $model->extendReservation('processing', 5, Unit::Minute);
     * @example
     * // Extend until a specific time
     * $model->extendReservation('processing', now()->addHour());
     */
    public function extendReservation(mixed $key, string|int|Carbon $duration = self::DEFAULT_RESERVATION_DURATION, Unit|string|null $unit = null): bool
    {
        $duration = $this->normalizeDuration($duration, $unit);
        $key = $this->disambiguateUserKey($key);
        $lockKey = $this->getReservationLockKey($key);
        $model = $this->getCacheLockModel();

        return (bool) $model::query()
            ->where('key', Cache::getPrefix().$lockKey)
            ->where('expiration', '>', Carbon::now()->timestamp)
            ->update(['expiration' => Carbon::now()->timestamp + $duration]);
    }

    /**
     * Release a reservation on this model.
     *
     * This forcefully releases the reservation, regardless of which process owns it.
     * Use with caution in distributed systems.
     *
     * @param  mixed  $key  The reservation key to release.
     *
     * @example
     * $model->reserve('processing');
     * // Do work...
     * $model->releaseReservation('processing');
     */
    public function releaseReservation(mixed $key): void
    {
        $this->reservation($key)->forceRelease();
    }

    /**
     * Check if this model is currently reserved.
     *
     * @param  mixed  $key  The reservation key to check.
     * @return bool True if the model is reserved, false if available.
     *
     * @example
     * if ($model->isReserved('processing')) {
     *     // Model is being processed by another worker
     * }
     */
    public function isReserved(mixed $key): bool
    {
        return $this->reserve($key, 0) === false;
    }

    /**
     * Normalize a duration value to seconds.
     *
     * Handles various duration formats:
     * - Integer: Used directly as seconds (or multiplied by unit if provided)
     * - String: Parsed as a Carbon date/time
     * - Carbon: Difference from now in seconds
     *
     * @param  string|int|Carbon  $duration  The duration to normalize.
     * @param  Unit|string|null  $unit  Optional time unit (Unit::Minute, 'minutes', etc.) when $duration is an integer.
     * @return int Duration in seconds (minimum 0).
     */
    protected function normalizeDuration(string|int|Carbon $duration, Unit|string|null $unit = null): int
    {
        if (is_string($duration)) {
            $duration = Carbon::make($duration);
        }

        if ($duration instanceof Carbon) {
            return max(0, (int) $duration->diffInSeconds(now()));
        }

        // If a unit is specified, convert using CarbonInterval
        if ($unit !== null && is_int($duration)) {
            if ($unit instanceof Unit) {
                $interval = $unit->interval($duration);
            } else {
                $interval = CarbonInterval::make("{$duration} {$unit}");
            }

            return max(0, (int) $interval->totalSeconds);
        }

        return max(0, (int) $duration);
    }

    /**
     * Generate the cache lock key for a reservation.
     *
     * The format is: reservation:{morphClass}:{modelKey}:{userKey}
     * This format is relied upon by the database via generated columns.
     *
     * @param  string  $key  The user-provided reservation key.
     * @return string The full cache lock key.
     */
    protected function getReservationLockKey(string $key): string
    {
        return "reservation:{$this->getMorphClass()}:{$this->getKey()}:{$key}";
    }

    /**
     * Get the model class used for cache locks.
     *
     * This allows the cache lock model to be swapped via configuration.
     *
     * @return string The fully qualified class name of the cache lock model.
     */
    protected function getCacheLockModel(): string
    {
        return config('reservable.model', CacheLock::class);
    }

    /**
     * Convert a user-provided key to a string identifier.
     *
     * @param  mixed  $key  The key to disambiguate (string, enum, or object).
     * @return string The string representation of the key.
     */
    protected function disambiguateUserKey(mixed $key): string
    {
        if ($key instanceof BackedEnum) {
            $key = (string) $key->value;
        } elseif ($key instanceof UnitEnum) {
            $key = $key->name;
        }

        if (is_object($key)) {
            $key = get_class($key);
        }

        return $key;
    }

    /**
     * Create a cache lock instance for this model.
     *
     * @param  mixed  $key  The reservation key.
     * @param  string|int|Carbon  $duration  Lock duration.
     * @param  Unit|string|null  $unit  Optional time unit (Unit::Minute, 'minutes', etc.) when $duration is an integer.
     * @return Lock The cache lock instance.
     */
    protected function reservation(mixed $key, string|int|Carbon $duration = self::DEFAULT_RESERVATION_DURATION, Unit|string|null $unit = null): Lock
    {
        $duration = $this->normalizeDuration($duration, $unit);
        $key = $this->disambiguateUserKey($key);

        // The database, via generated columns, relies on this format. Do not change it.
        return Cache::lock($this->getReservationLockKey($key), $duration);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to retrieve models and reserve them atomically.
     *
     * This scope filters to unreserved models and then attempts to reserve
     * each result. Only models that were successfully reserved are returned.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  mixed  $key  The reservation key.
     * @param  string|int|Carbon  $duration  Lock duration in seconds.
     * @param  Unit|string|null  $unit  Optional time unit (Unit::Minute, 'minutes', etc.) when $duration is an integer.
     *
     * @example
     * // Get up to 10 unreserved jobs and reserve them for 120 seconds
     * $jobs = Job::reserveFor('worker-1', 120)->limit(10)->get();
     * @example
     * // Get up to 10 unreserved jobs and reserve them for 5 minutes using Unit enum
     * $jobs = Job::reserveFor('worker-1', 5, Unit::Minute)->limit(10)->get();
     */
    public function scopeReserveFor(Builder $query, mixed $key, string|int|Carbon $duration = self::DEFAULT_RESERVATION_DURATION, Unit|string|null $unit = null): void
    {
        $key = $this->disambiguateUserKey($key);

        $query->unreserved($key)->afterQuery(function ($models) use ($key, $duration, $unit) {
            return $models
                ->filter(fn ($model) => $model->reserve($key, $duration, $unit))
                ->values();
        });
    }

    /**
     * Scope to filter models that are currently reserved.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  mixed  $key  The reservation key to check.
     *
     * @example
     * // Get all jobs currently being processed
     * $processingJobs = Job::reserved('processing')->get();
     */
    public function scopeReserved(Builder $query, mixed $key): void
    {
        $key = $this->disambiguateUserKey($key);

        $query->whereHas('reservations', function (Builder $query) use ($key) {
            return $query->where('type', $key);
        });
    }

    /**
     * Scope to filter models that are not currently reserved.
     *
     * @param  Builder  $query  The query builder instance.
     * @param  mixed  $key  The reservation key to check.
     *
     * @example
     * // Get all available jobs
     * $availableJobs = Job::unreserved('processing')->get();
     */
    public function scopeUnreserved(Builder $query, mixed $key): void
    {
        $key = $this->disambiguateUserKey($key);

        $query->whereDoesntHave('reservations', function (Builder $query) use ($key) {
            return $query->where('type', $key);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get all active reservations for this model.
     *
     * Returns a morphMany relationship to the cache lock model, filtered
     * to only include reservation-type locks that haven't expired.
     *
     * @return MorphMany The relationship instance.
     */
    public function reservations(): MorphMany
    {
        $model = $this->getCacheLockModel();

        return $this->morphMany($model, 'model')
            ->where('is_reservation', true)
            ->where('expiration', '>', Carbon::now()->timestamp);
    }
}
