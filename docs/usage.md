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

// Reserve with a string date expression
$video->reserve('processing', '+30 minutes');
```

The method returns `true` if the lock was acquired, or `false` if the model is already reserved with that key.

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

## Reservation Keys

### String Keys

The simplest approach is using string keys:

```php
$video->reserve('transcoding');
$video->reserve('thumbnail-generation');
$video->reserve('user:123:review');
```

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

The enum's `name` property is used as the key.

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

### Use Appropriate Durations

Set durations longer than your expected processing time, but not excessively long:

```php
// For a 2-minute job, reserve for 5 minutes
$video->reserve('processing', 300);
```

If a process crashes, the lock will automatically expire and allow retry.
