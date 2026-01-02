# Query Scopes

Reservable provides query scopes to efficiently filter models by their reservation status.

## unreserved()

Find models that are not currently reserved for a given key.

> **Note:** This scope returns point-in-time results. By the time you try to reserve a model, another process may have already reserved it. Use `reserveFor()` for atomic find-and-reserve operations.

```php
// Get all videos not reserved for processing
$available = Video::unreserved('processing')->get();

// Combine with other conditions
$available = Video::unreserved('processing')
    ->where('status', 'pending')
    ->orderBy('created_at')
    ->limit(10)
    ->get();
```

Works with enum keys:

```php
$available = Video::unreserved(JobType::Transcoding)->get();
```

## reserved()

Find models that are currently reserved for a given key:

```php
// Get all videos currently being processed
$processing = Video::reserved('processing')->get();

// Count reserved models
$count = Video::reserved('transcoding')->count();
```

## reserveFor()

Find unreserved models and reserve each with an atomic lock:

```php
// Get up to 5 unreserved videos and reserve them
$videos = Video::reserveFor('processing', 60)->limit(5)->get();
```

This scope:

1. Filters to unreserved models
2. Executes the query
3. Attempts to reserve each returned model
4. Filters out any models that couldn't be reserved (race conditions)
5. Returns only successfully reserved models

### How it Handles Race Conditions

If two workers run the same query simultaneously:

```php
// Worker A and Worker B both run:
$videos = Video::reserveFor('processing', 60)->limit(5)->get();
```

Both might initially see the same 5 unreserved videos. However:

1. Worker A reserves Video 1 first
2. Worker B tries to reserve Video 1 but fails (already reserved)
3. Worker B's result excludes Video 1

Each worker only receives videos they successfully reserved.

### Duration Parameter

```php
// Reserve for 60 seconds (default)
Video::reserveFor('processing')->get();

// Reserve for 5 minutes
Video::reserveFor('processing', 300)->get();

// Reserve until a specific time
Video::reserveFor('processing', now()->addHour())->get();
```

## Combining Scopes

Scopes can be combined with standard Eloquent methods:

```php
$videos = Video::unreserved('transcoding')
    ->where('needs_transcoding', true)
    ->where('file_size', '<', 1000000000) // < 1GB
    ->orderBy('priority', 'desc')
    ->limit(10)
    ->get();
```

## Scope with Relationships

```php
$videos = Video::unreserved('processing')
    ->with(['user', 'metadata'])
    ->whereHas('user', fn($q) => $q->where('plan', 'premium'))
    ->get();
```

## Performance Considerations

The scopes use generated columns in the `cache_locks` table for efficient querying. This means:

- Queries are performed directly in SQL
- No need to load all models and check locks in PHP
- Indexes can be added to the generated columns for large datasets

For optimal performance with large tables, consider adding an index:

```php
// In a migration
Schema::table('cache_locks', function (Blueprint $table) {
    $table->index(['model_type', 'model_id', 'type']);
});
```
