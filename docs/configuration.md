# Configuration

## Publishing the Config

To customize the package configuration:

```bash
php artisan vendor:publish --tag=reservable-config
```

This creates `config/reservable.php`.

## Configuration Options

### model

```php
'model' => AaronFrancis\Reservable\Models\CacheLock::class,
```

The Eloquent model representing cache locks. Override this to use a custom model.

#### Custom Model Example

```php
<?php

namespace App\Models;

use AaronFrancis\Reservable\Models\CacheLock as BaseCacheLock;

class CacheLock extends BaseCacheLock
{
    // Add custom functionality

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiration < now()->timestamp;
    }

    public function scopeExpired($query)
    {
        return $query->where('expiration', '<', now()->timestamp);
    }
}
```

Update config:

```php
'model' => App\Models\CacheLock::class,
```

## Full Configuration File

```php
<?php

// config/reservable.php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Lock Model
    |--------------------------------------------------------------------------
    |
    | The model that represents cache locks in the database. This model should
    | have the generated columns that parse the reservation key format.
    |
    */
    'model' => AaronFrancis\Reservable\Models\CacheLock::class,
];
```

## Database Configuration

### Cache Driver

Reservable requires the database cache driver. Configure in `.env`:

```env
CACHE_DRIVER=database
```

Or in `config/cache.php`:

```php
'default' => env('CACHE_DRIVER', 'database'),

'stores' => [
    'database' => [
        'driver' => 'database',
        'connection' => env('DB_CACHE_CONNECTION'),
        'table' => env('DB_CACHE_TABLE', 'cache'),
        'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
        'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks'),
        'lock_lottery' => [2, 100],
    ],
],
```

### Migration

The package migration adds generated columns to parse reservation keys. These columns enable efficient SQL queries:

| Column | Type | Description |
|--------|------|-------------|
| `is_reservation` | boolean | True if the key is a reservation lock |
| `model_type` | string | The morph class (e.g., `App\Models\Video`) |
| `model_id` | integer | The model's primary key |
| `type` | string | The reservation key (e.g., `processing`) |

The exact SQL for generated columns varies by database:

- **PostgreSQL**: Uses `split_part()` and `substring()`
- **MySQL**: Uses `SUBSTRING_INDEX()` and `LOCATE()`
- **SQLite**: Uses `substr()` and `instr()`

## Environment Variables

No package-specific environment variables are required. Standard Laravel cache configuration applies:

```env
CACHE_DRIVER=database
DB_CACHE_CONNECTION=null
DB_CACHE_TABLE=cache
DB_CACHE_LOCK_CONNECTION=null
DB_CACHE_LOCK_TABLE=cache_locks
```
