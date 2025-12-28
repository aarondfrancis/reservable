<?php

/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace AaronFrancis\Reservable;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

trait Reservable
{
    public function reserve(mixed $key, string|int|Carbon $duration = 60): bool
    {
        return $this->reservation($key, $duration)->get();
    }

    public function blockingReserve(mixed $key, string|int|Carbon $duration = 60, int $wait = 10): bool
    {
        try {
            return $this->reservation($key, $duration)->block($wait);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return false;
        }
    }

    public function reserveWhile(mixed $key, string|int|Carbon $duration, callable $callback): mixed
    {
        return $this->reservation($key, $duration)->get(function () use ($callback) {
            return $callback($this);
        });
    }

    public function extendReservation(mixed $key, string|int|Carbon $duration = 60): bool
    {
        if (is_string($duration)) {
            $duration = Carbon::make($duration);
        }

        if ($duration instanceof Carbon) {
            $duration = max(0, $duration->diffInSeconds(now()));
        }

        $key = $this->disambiguateUserKey($key);
        $lockKey = "reservation:{$this->getMorphClass()}:{$this->getKey()}:{$key}";

        $model = config('reservable.model', CacheLock::class);

        return (bool) $model::query()
            ->where('key', Cache::getPrefix().$lockKey)
            ->where('expiration', '>', Carbon::now()->timestamp)
            ->update(['expiration' => Carbon::now()->timestamp + $duration]);
    }

    public function releaseReservation(mixed $key): void
    {
        $this->reservation($key)->forceRelease();
    }

    public function isReserved(mixed $key): bool
    {
        return $this->reserve($key, 0) === false;
    }

    protected function disambiguateUserKey(mixed $key): string
    {
        if ($key instanceof UnitEnum) {
            $key = $key->name;
        }

        if (is_object($key)) {
            $key = get_class($key);
        }

        return $key;
    }

    protected function reservation(mixed $key, string|int|Carbon $duration = 60): Lock
    {
        if (is_string($duration)) {
            $duration = Carbon::make($duration);
        }

        if ($duration instanceof Carbon) {
            $duration = max(0, $duration->diffInSeconds(now()));
        }

        $key = $this->disambiguateUserKey($key);

        // The database, via generated columns, relies on this format. Do not change it.
        return Cache::lock("reservation:{$this->getMorphClass()}:{$this->getKey()}:{$key}", $duration);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeReserveFor(Builder $query, mixed $key, string|int|Carbon $duration = 60): void
    {
        $key = $this->disambiguateUserKey($key);

        $query->unreserved($key)->afterQuery(function ($models) use ($key, $duration) {
            return $models
                ->filter(fn ($model) => $model->reserve($key, $duration))
                ->values();
        });
    }

    public function scopeReserved(Builder $query, mixed $key): void
    {
        $key = $this->disambiguateUserKey($key);

        $query->whereHas('reservations', function (Builder $query) use ($key) {
            return $query->where('type', $key);
        });
    }

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
    public function reservations(): MorphMany
    {
        $model = config('reservable.model', CacheLock::class);

        return $this->morphMany($model, 'model')
            ->where('is_reservation', true)
            ->where('expiration', '>', Carbon::now()->timestamp);
    }
}
