# Reservable

Eloquent model reservation/locking through Laravel's cache lock system.

This package allows you to temporarily "reserve" Eloquent models using Laravel's atomic cache locks. This is useful when you need to ensure exclusive access to a model for a period of time, such as during background processing.

## Installation

```bash
composer require aaronfrancis/reservable
```

Publish the migration:

```bash
php artisan vendor:publish --tag=reservable-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=reservable-config
```

## Requirements

This package requires Laravel's database cache driver. Make sure your `cache_locks` table exists (created by Laravel's default cache migration).

The published migration adds generated columns to parse reservation keys into queryable columns. This allows efficient querying of reserved/unreserved models.

**Supported databases:** PostgreSQL, MySQL/MariaDB, SQLite (limited support)

## Usage

Add the `Reservable` trait to your model:

```php
use AaronFrancis\Reservable\Reservable;

class Video extends Model
{
    use Reservable;
}
```

### Reserve a model

```php
// Reserve for 60 seconds (default)
$video->reserve('processing');

// Reserve for a specific duration
$video->reserve('processing', 300); // 5 minutes

// Reserve until a specific time
$video->reserve('processing', now()->addHour());

// Reserve with a string date
$video->reserve('processing', '+30 minutes');
```

The `reserve()` method returns `true` if the lock was acquired, or `false` if the model is already reserved.

### Check if reserved

```php
if ($video->isReserved('processing')) {
    // Model is currently reserved
}
```

### Release a reservation

```php
$video->releaseReservation('processing');
```

### Query scopes

Find unreserved models:

```php
$available = Video::unreserved('processing')->get();
```

Find reserved models:

```php
$reserved = Video::reserved('processing')->get();
```

> **Note:** These scopes return point-in-time results. By the time you try to reserve a model from the results, another process may have already reserved it. Use `reserveFor` for atomic find-and-reserve operations.

Find and reserve in one query:

```php
// Get unreserved models and reserve them atomically
$videos = Video::reserveFor('processing', 60)->limit(5)->get();
```

The `reserveFor` scope filters to unreserved models, then atomically reserves each one that's returned. Models that can't be reserved (race condition) are filtered out.

### Key types

Reservation keys can be strings, enums, or objects:

```php
// String key
$video->reserve('processing');

// Enum key
$video->reserve(JobType::Transcoding);

// Object key (uses class name)
$video->reserve($someService);
```

### Multiple reservation types

A model can have multiple different reservation types simultaneously:

```php
$video->reserve('transcoding');
$video->reserve('thumbnail-generation');

$video->isReserved('transcoding'); // true
$video->isReserved('thumbnail-generation'); // true
$video->isReserved('uploading'); // false
```

## How it works

Reservable uses Laravel's cache lock system with a specially formatted key:

```
reservation:{morph_class}:{model_id}:{key}
```

The migration adds generated columns that parse this key format, enabling efficient SQL queries to filter reserved/unreserved models without needing to check each lock individually.

## Configuration

```php
// config/reservable.php

return [
    // The model representing cache locks
    'model' => AaronFrancis\Reservable\CacheLock::class,
];
```

## Testing

```bash
composer test
```

## License

MIT
