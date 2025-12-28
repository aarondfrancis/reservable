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

Reservable builds on Laravel's atomic cache locks, which use the `cache_locks` table to provide database-level mutual exclusion.

### Lock key format

When you call `$video->reserve('processing')`, Reservable creates a cache lock with a specially formatted key:

```
reservation:{morph_class}:{model_id}:{reservation_key}
```

For example: `reservation:App\Models\Video:42:processing`

### Generated columns

The challenge with cache locks is that they're not inherently queryable by model. You can't efficiently ask "give me all Videos that aren't locked" because the lock table doesn't know about your models.

Reservable solves this by adding **generated columns** to the `cache_locks` table that parse the key format:

| Column | Extracted From | Example Value |
|--------|----------------|---------------|
| `is_reservation` | Key contains `reservation:` | `true` |
| `model_type` | Second segment | `App\Models\Video` |
| `model_id` | Third segment | `42` |
| `type` | Fourth segment | `processing` |

These columns are computed automatically by the database whenever a row is inserted or updated. The exact SQL varies by database engine (PostgreSQL uses `split_part()`, MySQL uses `SUBSTRING_INDEX()`, SQLite uses `substr()`).

### Efficient queries

With generated columns in place, the `reserved()` and `unreserved()` scopes become simple JOIN queries:

```sql
-- Find unreserved videos
SELECT * FROM videos
WHERE NOT EXISTS (
    SELECT 1 FROM cache_locks
    WHERE model_type = 'App\Models\Video'
    AND model_id = videos.id
    AND type = 'processing'
    AND expiration > UNIX_TIMESTAMP()
)
```

This is much more efficient than fetching all videos and checking each lock individually in PHP.

### Atomic reservations

The `reserve()` method uses Laravel's `Lock::get()` which performs an atomic database operation—either the lock is acquired or it isn't. There's no window where two processes can both think they have the lock.

The `reserveFor` scope combines this with query filtering: it finds unreserved models, then attempts to reserve each one, filtering out any that fail due to race conditions.

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
