# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v3.4.1] - 2026-08-15

### Fixed

- Storage request URLs could contain a double slash for root-level paths (`https://{region}.storage.bunnycdn.com/{zone}//`), which the Bunny API rejects — root listings (e.g. Laravel's `allFiles()`) could come back empty ([#75](https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/75)). Paths are now normalized in a single place, and a regression test asserts the exact request URI.

## [v3.4.0] - 2026-08-15

### Added

- **Root path support** — the third `BunnyCDNAdapter` constructor argument is now a `root` path prefix, scoping all operations (write, read, list, delete, move, copy, URLs) to a subdirectory of the storage zone. Listed paths are root-relative; public and temporary URLs are root-scoped and signed against the root-scoped path. Resolves the long-requested "root" config support ([#96](https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/96), [discussion #30](https://github.com/PlatformCommunity/flysystem-bunnycdn/discussions/30)).
- **Laravel-compatible temporary URLs** — `BunnyCDNAdapter::getTemporaryUrl()` implements the method Laravel's `FilesystemAdapter` looks for, so `Storage::disk('bunnycdn')->temporaryUrl()` now works out of the box. Accepts a `DateTimeInterface` or minutes-from-now integer, plus additional query parameters that are signed into the URL ([#97](https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/97)).

### Changed

- PHP floor raised to `^8.3` (PHP 8.2 reaches end-of-life 31 December 2026).
- Replaced the abandoned `fzaninotto/faker` with `fakerphp/faker` (drop-in, same namespace).
- `laravel/pint` `^0.2.3` → `^1.30` (fixes PHP 8.5 deprecation warnings).
- `phpunit/phpunit` `^10.0` → `^11.5` (10.x is end-of-life; PHPUnit 12+ drops the doc-comment metadata support that `flysystem-adapter-test-utilities` relies on).
- PHPStan raised from the default level 0 to level 5 via `phpstan.neon.dist`; reported issues fixed.
- Test methods migrated from `@test` doc-comments to `#[Test]` attributes (deprecated in PHPUnit 11).
- CI: `actions/checkout@v7`, `shivammathur/setup-php@v2`, `actions/cache@v6`, `codecov/codecov-action@v7` (replacing the deprecated bash uploader); PHP matrix `8.3`/`8.4`/`8.5`; PHPStan and Pint steps guarded so the shared workflow keeps working on the legacy `v1`/`v2` branches.

### Fixed

- `deleteDirectory('')` could send a `DELETE` for the storage zone root — now guarded with `UnableToDeleteDirectory`.
- Files whose content is numeric or boolean (e.g. `123`) no longer fail `download()` with a `TypeError` — non-array JSON payloads return the raw body.
- `parse_bunny_timestamp()` no longer fatals on malformed timestamps (falls back to `0`).
- `writeBatch()` failures now report the failing target path instead of a numeric batch index.
- `lastModified()` / `fileSize()` on directories now throw `UnableToRetrieveMetadata` explicitly instead of relying on a return-type `TypeError`.

### Tests

- New `RootTest` — full flysystem conformance suite against a root-scoped adapter, plus targeted root tests (trailing-slash normalization, root scoping, root-relative listings, root-signed temporary URLs, `writeBatch` with root, root + `PathPrefixedAdapter` composition).
- Regression tests for the root-deletion guard, malformed timestamps, `request()` JSON decode edge cases, and full-URL temporary URLs with a root configured.
- Suite grew from 158 to 235 tests.

## [v3.3.10] - 2026-02-04

### Added

- Ability to add query params to a temporary URL ([#95](https://github.com/PlatformCommunity/flysystem-bunnycdn/pull/95)).
- Pull zone URL to temporary URL generation ([#93](https://github.com/PlatformCommunity/flysystem-bunnycdn/pull/93)).

## [v3.3.8] - 2026-01-23

### Added

- Support for temporary URLs ([#88](https://github.com/PlatformCommunity/flysystem-bunnycdn/pull/88)).

## [v3.3.5] - 2024-11-07

### Fixed

- Checksum and metadata handling fixes.

## Earlier releases

For releases prior to v3.3.5, see the [GitHub releases page](https://github.com/PlatformCommunity/flysystem-bunnycdn/releases).

[v3.4.1]: https://github.com/PlatformCommunity/flysystem-bunnycdn/compare/v3.4.0...v3.4.1
[v3.4.0]: https://github.com/PlatformCommunity/flysystem-bunnycdn/compare/v3.3.10...v3.4.0
[v3.3.10]: https://github.com/PlatformCommunity/flysystem-bunnycdn/compare/v3.3.8...v3.3.10
[v3.3.8]: https://github.com/PlatformCommunity/flysystem-bunnycdn/compare/v3.3.6...v3.3.8
[v3.3.5]: https://github.com/PlatformCommunity/flysystem-bunnycdn/releases/tag/v3.3.5
