# Usage

## Reserving a Model

Use the `reserve()` method to acquire a lock on a model:

```php
// Reserve for 60 seconds (default)
$video->reserve('processing');

// Reserve for a specific number of seconds
$video->reserve('processing', 300); // 5 minutes

// Reserve until a specific time
$video->reserve('processing', now()->addHour());
```

The method returns `true` if the lock was acquired, or `false` if the model is already reserved with that key.

## Interval Helpers

Instead of calculating seconds, use Laravel's interval helper functions:

```php
use function Illuminate\Support\minutes;
use function Illuminate\Support\hours;
use function Illuminate\Support\days;
use function Illuminate\Support\weeks;

$video->reserve('processing', minutes(5));
$video->reserve('processing', hours(2));
$video->reserve('processing', days(1));
$video->reserve('processing', weeks(1));
```

These helpers return `DateInterval` objects and work with all duration-accepting methods: `reserve()`, `blockingReserve()`, `reserveWhile()`, `extendReservation()`, and `reserveFor()` scope.

```php
if ($video->reserve('processing')) {
    // Lock acquired, proceed with work
} else {
    // Already reserved by another process
}
```

## Checking Reservation Status

Use `isReserved()` to check if a model currently has an active reservation:

```php
if ($video->isReserved('processing')) {
    // Model is currently reserved
}
```

## Releasing a Reservation

Manually release a reservation before it expires:

```php
$video->releaseReservation('processing');
```

Releasing a non-existent reservation is safe and won't throw an error.

## Blocking Reserve

If you need to wait for a lock to become available:

```php
// Wait up to 10 seconds (default) for the lock
$video->blockingReserve('processing', 60);

// Wait up to 30 seconds for the lock
$video->blockingReserve('processing', 60, 30);

// With interval helpers
$video->blockingReserve('processing', minutes(5), 30);
```

Returns `true` if the lock was acquired, `false` if the wait time expired without acquiring the lock.

## Reserve with Callback

For guaranteed cleanup, use `reserveWhile()` to automatically release the lock:

```php
$result = $video->reserveWhile('processing', 300, function ($video) {
    // Do your work here...
    return $video->transcode();
}); // Lock is automatically released

if ($result === false) {
    // Lock couldn't be acquired
}
```

The lock is released even if the callback throws an exception, preventing orphaned locks.

## Extending a Reservation

If a job takes longer than expected, extend the reservation without releasing it:

```php
$video->reserve('processing', 60);

// Halfway through, realize you need more time
$video->extendReservation('processing', 120); // Add 2 more minutes
```

This is safer than releasing and re-reserving, which could fail if another process grabs the lock.

Returns `false` if no active reservation exists for that key.

## Reservation Keys

### String Keys

The simplest approach is using string keys:

```php
$video->reserve('transcoding');
$video->reserve('thumbnail-generation');
$video->reserve('user:123:review');
```

> **Note:** Colons (`:`) in keys are internally replaced with underscores (`_`) to avoid conflicts with the lock key format. This happens transparently—you can still use colons when reserving and checking, but the stored key will use underscores.

### Enum Keys

Enums provide type safety and IDE autocompletion:

```php
enum JobType
{
    case Transcoding;
    case ThumbnailGeneration;
    case Upload;
}

$video->reserve(JobType::Transcoding);
$video->isReserved(JobType::Transcoding); // true
```

For pure enums (UnitEnum), the enum's `name` property is used as the key.

### Backed Enum Keys

For backed enums, the backing **value** is used instead of the name:

```php
enum Status: string
{
    case Processing = 'proc';
    case Uploading = 'upload';
}

$video->reserve(Status::Processing);

// The backing value 'proc' is used, not 'Processing'
$video->isReserved(Status::Processing); // true
$video->isReserved('proc');             // true
$video->isReserved('Processing');       // false
```

### Object Keys

Pass any object to use its class name as the key:

```php
$video->reserve($transcodingService);
// Uses: "App\Services\TranscodingService" as the key
```

## Multiple Reservation Types

A model can have multiple different reservation types simultaneously:

```php
$video->reserve('transcoding');
$video->reserve('thumbnail-generation');

$video->isReserved('transcoding');           // true
$video->isReserved('thumbnail-generation');  // true
$video->isReserved('uploading');             // false
```

Each reservation type is independent. Releasing one doesn't affect others:

```php
$video->releaseReservation('transcoding');

$video->isReserved('transcoding');           // false
$video->isReserved('thumbnail-generation');  // true (still reserved)
```

## Accessing Reservations

The `reservations` relationship returns all active reservations for a model:

```php
$reservations = $video->reservations;

foreach ($reservations as $reservation) {
    echo $reservation->type;       // e.g., "transcoding"
    echo $reservation->expiration; // Unix timestamp
}
```

This only returns non-expired reservations.

## Best Practices

### Use Descriptive Keys

```php
// Good
$order->reserve('checkout:user:' . $userId);
$video->reserve('transcode:1080p');

// Less descriptive
$order->reserve('lock');
```

### Handle Reservation Failures

```php
if (!$video->reserve('processing', 300)) {
    // Log, retry later, or notify
    Log::warning('Could not reserve video', ['id' => $video->id]);
    return;
}

try {
    // Do work...
} finally {
    $video->releaseReservation('processing');
}
```

Or use `reserveWhile()` for automatic cleanup:

```php
$video->reserveWhile('processing', 300, function ($video) {
    // Do work... lock is auto-released when done
});
```

### Use Appropriate Durations

Set durations longer than your expected processing time, but not excessively long:

```php
// For a 2-minute job, reserve for 5 minutes
$video->reserve('processing', 300);
```

If a process crashes, the lock will automatically expire and allow retry.
