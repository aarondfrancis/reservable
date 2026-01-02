# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- Database cache store configured

**Supported databases:**
- PostgreSQL
- MySQL/MariaDB
- SQLite 3.31+ (generated columns required)

## Install via Composer

```bash
composer require aaronfrancis/reservable
```

## Publish the Migration

The package requires a migration that adds generated columns to parse reservation keys:

```bash
php artisan vendor:publish --tag=reservable-migrations
php artisan migrate
```

This migration modifies the `cache_locks` table to add columns that allow efficient querying of reserved models.

## Publish Configuration (Optional)

If you need to customize the cache lock model:

```bash
php artisan vendor:publish --tag=reservable-config
```

This creates `config/reservable.php` where you can specify a custom model.

## Database Cache Setup

Reservable requires Laravel's database cache store. Ensure you have the cache tables:

```bash
php artisan make:cache-table
php artisan migrate
```

Then set your cache store in `.env`:

```env
CACHE_STORE=database
```

Or in `config/cache.php`:

```php
'default' => env('CACHE_STORE', 'database'),
```

## Add the Trait

Add the `Reservable` trait to any model you want to make reservable:

```php
<?php

namespace App\Models;

use AaronFrancis\Reservable\Concerns\Reservable;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use Reservable;
}
```

That's it! Your model now supports reservations.
