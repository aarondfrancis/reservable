# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Duration parameters now accept `int`, `DateInterval`, or `Carbon` instead of separate `$duration` and `$unit` arguments
- Use `CarbonInterval::minutes(5)` instead of `minutes(5)` helper (compatible with Laravel 11+)

## [0.1.1] - 2025-12-28

### Added
- MIT LICENSE file
- `pint.json` for code style configuration
- `laravel/pint` as dev dependency with `composer lint` script

### Changed
- Moved `Reservable` trait to `AaronFrancis\Reservable\Concerns\Reservable`
- Moved `CacheLock` model to `AaronFrancis\Reservable\Models\CacheLock`
- Refactored `ReservableServiceProvider` to use proper `register()` and `boot()` separation


## [0.1.0] - 2025-12-28

### Added
- `Reservable` trait for Eloquent models with cache-based locking
- `CacheLock` model for tracking reservations
- `reserveFor()` method to reserve a model for a specific owner
- `reserved()` and `unreserved()` query scopes
- Configurable model and table names via config
- Database migrations for cache locks table
- CI testing for SQLite, MySQL, and PostgreSQL 17


[Unreleased]: https://github.com/aarondfrancis/reservable/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/aarondfrancis/reservable/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/aarondfrancis/reservable/releases/tag/v0.1.0
