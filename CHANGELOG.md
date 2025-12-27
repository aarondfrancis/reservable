# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Reservable` trait for Eloquent models with cache-based locking
- `CacheLock` model for tracking reservations
- `reserveFor()` method to reserve a model for a specific owner
- `reserved()` and `unreserved()` query scopes
- Configurable model and table names via config
- Database migrations for cache locks table
- CI testing for SQLite, MySQL, and PostgreSQL 17

[Unreleased]: https://github.com/aarondfrancis/reservable/compare/main...HEAD
