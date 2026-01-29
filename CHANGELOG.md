# Changelog

All notable changes to Dead Drop will be documented in this file.

## 0.3.2 - 2026-01-29
- Added `date_column` config option to specify custom date column for filtering (defaults to `created_at`, set to `false` to disable)

## 0.3.1 - 2026-01-23
- Added ability to specify table `primary_key`

## 0.3.0 - 2026-01-23

### Fixed
- Export jobs now properly dispatch to configured queue
- Consistent queue routing across all job types

### Added
- `queue_name` config option for specifying target queue

### Changed
- Jobs configure connection and queue in constructor (removed redundant dispatch chaining)
- Streamlined config comments and export file headers
- Updated README with migration publish instructions

## 0.2.0 - 2026-01-07

### Added
- Interactive date range filtering in CLI export command
  - Presets: Today, Yesterday, Last 7 days, Last 30 days
  - Custom intervals with natural language dates via Carbon
  - Works for single table and batch exports
- DeadDrop Facade for cleaner programmatic API
  - `DeadDrop::export('users')` instead of `app(Exporter::class)->exportTable()`
  - Support for date parameters and config overrides
  - Ideal for Nova/Filament integrations

### Changed
- `Exporter::exportTable()` accepts optional `$overrides` parameter for runtime filtering
- `Exporter::exportAll()` accepts optional `$overrides` parameter for batch date filtering
- Where conditions merge (append), other options replace

## 0.1.0 - 2026-01-07

Initial release.

### Added
- Export database tables to SQL with upsert syntax
- Import SQL files with transaction support
- Data anonymization using Faker with auto-detection
- Custom Faker method support
- Default values with automatic password hashing
- Cloud storage support (S3, Spaces)
- Multi-database connection support
- SQLite, MySQL, and PostgreSQL support
- Interactive CLI commands
- Programmatic API
