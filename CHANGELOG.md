# Changelog

All notable changes to `dead-drop` will be documented in this file.

## 0.2.0 - 2026-01-07

### Added
- Interactive date range filtering in CLI export command
  - Preset options: Today, Yesterday, Last 7 days, Last 30 days
  - Custom interval with natural language dates via Carbon::parse()
  - Examples for custom: "yesterday", "last month", "30 days ago", "2024-01-01"
  - Defaults to "Today" for quick exports
  - Works for both single table and batch (exportAll) exports
- DeadDrop Facade for cleaner programmatic API
  - `DeadDrop::export('users')` instead of `app(Exporter::class)->exportTable()`
  - Support for date parameters and config overrides
  - Perfect for Nova/Filament admin panel integrations

### Changed
- Exporter::exportTable() now accepts optional $overrides parameter
  - Allows runtime filtering without modifying config
  - Where conditions are merged (appended), other options are replaced
  - Fully backward compatible
- Exporter::exportAll() now accepts optional $overrides parameter
  - Apply date filters to all tables in batch export
  - Same merging behavior as exportTable()

## 0.1.0 - 2026-01-07

Initial beta release

### Added
- Export database tables to SQL files with upsert syntax
- Import SQL files with transaction support
- Data anonymization using Faker
- Auto-detection of sensitive fields
- Custom Faker method support
- Default values for required fields
- Automatic password hashing for defaults
- Cloud storage support (S3, Spaces, etc.)
- Multi-database connection support
- Support for SQLite, MySQL, and PostgreSQL
- Interactive CLI commands
- Programmatic API for exports and imports
