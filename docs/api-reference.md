# API Reference

## Reservable Trait

The `Reservable` trait provides all reservation functionality to your Eloquent models.

### Methods

#### reserve()

```php
public function reserve(
    mixed $key,
    string|int|Carbon $duration = 60,
    Unit|string|null $unit = null
): bool
```

Attempt to acquire a reservation lock on the model.

**Parameters:**
- `$key` — The reservation key (string, enum, or object)
- `$duration` — Lock duration in seconds, Carbon instance, or string date expression. Default: 60 seconds
- `$unit` — Optional time unit when `$duration` is an integer. Accepts `Carbon\Unit` enum (e.g., `Unit::Minute`) or string (e.g., `'minutes'`)

**Returns:** `true` if lock acquired, `false` if already reserved

**Example:**
```php
$video->reserve('processing');           // 60 seconds
$video->reserve('processing', 300);      // 5 minutes
$video->reserve('processing', now()->addHour());
$video->reserve('processing', '+30 minutes');

// Using Carbon\Unit enum
$video->reserve('processing', 5, Unit::Minute);
$video->reserve('processing', 2, Unit::Hour);

// Using string units
$video->reserve('processing', 5, 'minutes');
$video->reserve('processing', 2, 'hours');
```

---

#### isReserved()

```php
public function isReserved(mixed $key): bool
```

Check if the model is currently reserved with the given key.

**Parameters:**
- `$key` — The reservation key to check

**Returns:** `true` if reserved, `false` otherwise

**Example:**
```php
if ($video->isReserved('processing')) {
    // Model is locked
}
```

---

#### releaseReservation()

```php
public function releaseReservation(mixed $key): void
```

Release an existing reservation. Safe to call even if no reservation exists.

**Parameters:**
- `$key` — The reservation key to release

**Example:**
```php
$video->releaseReservation('processing');
```

---

#### blockingReserve()

```php
public function blockingReserve(
    mixed $key,
    string|int|Carbon $duration = 60,
    Unit|string|null $unit = null,
    int $wait = 10
): bool
```

Wait for a lock to become available instead of failing immediately.

**Parameters:**
- `$key` — The reservation key (string, enum, or object)
- `$duration` — Lock duration in seconds, Carbon instance, or string date expression. Default: 60 seconds
- `$unit` — Optional time unit when `$duration` is an integer. Accepts `Carbon\Unit` enum or string
- `$wait` — Maximum seconds to wait for the lock. Default: 10 seconds

**Returns:** `true` if lock acquired, `false` if wait time expired

**Example:**
```php
$video->blockingReserve('processing', 60, null, 30); // Wait up to 30 seconds

// Using Carbon\Unit enum
$video->blockingReserve('processing', 5, Unit::Minute, 30);

// Using string units
$video->blockingReserve('processing', 2, 'hours', 30);
```

---

#### reserveWhile()

```php
public function reserveWhile(
    mixed $key,
    string|int|Carbon $duration,
    callable|Unit|string|null $callbackOrUnit = null,
    ?callable $callback = null
): mixed
```

Acquire a lock, execute a callback, then automatically release the lock.

**Parameters:**
- `$key` — The reservation key (string, enum, or object)
- `$duration` — Lock duration in seconds, Carbon instance, or string date expression
- `$callbackOrUnit` — Either the callback, or a time unit (Unit enum or string) when using the 4-argument form
- `$callback` — The callback when using the 4-argument form with a unit

**Returns:** The callback's return value, or `false` if lock couldn't be acquired

**Signatures:**
```php
// 3-argument form (no unit)
$model->reserveWhile($key, $duration, $callback);

// 4-argument form (with unit)
$model->reserveWhile($key, $duration, $unit, $callback);
```

**Example:**
```php
$result = $video->reserveWhile('processing', 300, function ($video) {
    return $video->transcode();
});

// Using Carbon\Unit enum
$result = $video->reserveWhile('processing', 5, Unit::Minute, function ($video) {
    return $video->transcode();
});
```

---

#### extendReservation()

```php
public function extendReservation(
    mixed $key,
    string|int|Carbon $duration = 60,
    Unit|string|null $unit = null
): bool
```

Extend an existing reservation without releasing it. Useful for long-running jobs that need more time.

**Parameters:**
- `$key` — The reservation key to extend
- `$duration` — Additional time in seconds, Carbon instance, or string date expression. Default: 60 seconds
- `$unit` — Optional time unit when `$duration` is an integer. Accepts `Carbon\Unit` enum or string

**Returns:** `true` if reservation was extended, `false` if no active reservation exists

**Example:**
```php
$video->reserve('processing', 60);
// ... work takes longer than expected ...
$video->extendReservation('processing', 60); // Add 60 more seconds

// Using Carbon\Unit enum
$video->extendReservation('processing', 5, Unit::Minute);

// Using string units
$video->extendReservation('processing', 1, 'hour');
```

---

### Query Scopes

#### scopeReserved()

```php
public function scopeReserved(Builder $query, mixed $key): void
```

Filter to models that have an active reservation for the given key.

**Example:**
```php
$reserved = Video::reserved('processing')->get();
```

---

#### scopeUnreserved()

```php
public function scopeUnreserved(Builder $query, mixed $key): void
```

Filter to models that do not have an active reservation for the given key.

**Example:**
```php
$available = Video::unreserved('processing')->get();
```

---

#### scopeReserveFor()

```php
public function scopeReserveFor(
    Builder $query,
    mixed $key,
    string|int|Carbon $duration = 60,
    Unit|string|null $unit = null
): void
```

Find unreserved models and atomically reserve them. Models that fail to reserve (race conditions) are filtered from results.

**Parameters:**
- `$key` — The reservation key
- `$duration` — Lock duration. Default: 60 seconds
- `$unit` — Optional time unit when `$duration` is an integer. Accepts `Carbon\Unit` enum or string

**Example:**
```php
$videos = Video::reserveFor('processing', 300)->limit(5)->get();

// Using Carbon\Unit enum
$videos = Video::reserveFor('processing', 5, Unit::Minute)->limit(5)->get();
```

---

### Relationships

#### reservations()

```php
public function reservations(): MorphMany
```

Returns all active (non-expired) reservations for the model.

**Returns:** `MorphMany` relationship to `CacheLock` models

**Example:**
```php
foreach ($video->reservations as $reservation) {
    echo $reservation->type;
    echo $reservation->expiration;
}
```

---

## CacheLock Model

The `CacheLock` model represents entries in the `cache_locks` table.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `key` | string | The full cache lock key (primary key) |
| `owner` | string | The lock owner identifier |
| `expiration` | int | Unix timestamp when lock expires |
| `is_reservation` | bool | Whether this is a reservation lock (generated) |
| `model_type` | string | The morph class of the reserved model (generated) |
| `model_id` | int | The ID of the reserved model (generated) |
| `type` | string | The reservation key/type (generated) |

### Notes

- The model has no timestamps
- Uses `key` as string primary key
- Generated columns are computed from the `key` format

---

## Key Format

Reservation keys follow this format:

```
{cache_prefix}reservation:{morph_class}:{model_id}:{reservation_key}
```

For example:
```
laravel_cache_reservation:App\Models\Video:123:processing
```

The generated columns parse this format to enable efficient SQL queries.
