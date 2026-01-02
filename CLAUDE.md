# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@.claude/rules/laravel-package.md

## Package Overview

Reservable provides Eloquent model reservation/locking through Laravel's cache lock system. It allows temporary "reservation" of models using atomic cache locks, useful for ensuring exclusive access during background processing.

## Commands

```bash
composer test          # Run Pest tests (SQLite by default)
composer lint          # Run Laravel Pint

# Run single test file
vendor/bin/pest tests/ReservableTest.php

# Run specific test by name
vendor/bin/pest --filter="can reserve a model"

# Test with specific database (set env vars first)
DB_CONNECTION=pgsql vendor/bin/pest
DB_CONNECTION=mysql vendor/bin/pest
```

## Architecture

### Core Components

**`Reservable` trait** (`src/Concerns/Reservable.php`):
- Provides `reserve()`, `blockingReserve()`, `reserveWhile()`, `extendReservation()`, `releaseReservation()`, `isReserved()` methods
- Query scopes: `reserved()`, `unreserved()`, `reserveFor()`
- `reservations()` relationship returns active `CacheLock` models
- Lock key format: `reservation:{morphClass}:{modelKey}:{userKey}` - this format is relied upon by database generated columns

**`CacheLock` model** (`src/Models/CacheLock.php`):
- Maps to Laravel's `cache_locks` table
- Uses string primary key (`key` column), no timestamps, no incrementing
- Has generated columns (`is_reservation`, `model_type`, `model_id`, `type`) for efficient querying

**Migration** (`database/migrations/add_reservation_columns_to_cache_locks_table.php.stub`):
- Adds generated columns to `cache_locks` table using database-specific SQL
- Supports PostgreSQL (`split_part`), MySQL/MariaDB (`SUBSTRING_INDEX`), SQLite (`substr`/`instr`)
- SQLite requires table recreation since it doesn't support `ADD COLUMN` with `GENERATED`

### Key Design Decisions

- Uses `illuminate/*` packages, not `laravel/framework`
- Duration parameters accept `int` (seconds), `DateInterval`/`CarbonInterval`, or `Carbon` (absolute time)
- Colons in user-provided keys are replaced with underscores to avoid conflicts with lock key format
- Tests use `DatabaseMigrations` (not `RefreshDatabase`) due to PostgreSQL generated column issues with transactions

### Test Structure

Tests are in `tests/` with fixtures in `tests/database/migrations/` and `tests/Models/`. The `TestCase` configures SQLite, PostgreSQL, and MySQL connections based on `DB_CONNECTION` env var. CI runs against all three databases.
