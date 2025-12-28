# Reservable

Eloquent model reservation/locking through Laravel's cache lock system.

## What is Reservable?

Reservable allows you to temporarily "reserve" Eloquent models using Laravel's atomic cache locks. This is useful when you need to ensure exclusive access to a model for a period of time, such as during background processing.

## Key Features

- **Atomic Reservations** — Uses Laravel's cache locks for race-condition-safe reservations
- **Flexible Duration** — Reserve for seconds, until a specific time, or with string dates
- **Query Scopes** — Find and filter reserved/unreserved models efficiently
- **Multiple Reservation Types** — A model can have multiple different reservation types simultaneously
- **Key Flexibility** — Use strings, enums, or objects as reservation keys
- **Database Support** — Works with PostgreSQL, MySQL/MariaDB, and SQLite

## Use Cases

- **Background Job Processing** — Reserve a video for transcoding so no other worker picks it up
- **Checkout Systems** — Reserve inventory items while a user completes checkout
- **Resource Allocation** — Temporarily lock resources during multi-step operations
- **Rate Limiting** — Prevent duplicate processing of the same record

## Quick Example

```php
use AaronFrancis\Reservable\Concerns\Reservable;

class Video extends Model
{
    use Reservable;
}

// Reserve a video for processing
$video->reserve('transcoding', 300); // 5 minutes

// Check if reserved
if ($video->isReserved('transcoding')) {
    // Already being processed
}

// Find available videos
$available = Video::unreserved('transcoding')->limit(5)->get();

// Find and reserve in one atomic operation
$videos = Video::reserveFor('transcoding', 60)->limit(5)->get();
```

## How It Works

Reservable uses Laravel's cache lock system with a specially formatted key:

```
reservation:{morph_class}:{model_id}:{key}
```

The package migration adds generated columns to the `cache_locks` table that parse this key format. This enables efficient SQL queries to filter reserved/unreserved models without checking each lock individually.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Database cache driver (PostgreSQL, MySQL, or SQLite)
