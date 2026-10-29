# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.7.0] - 2026-10-28

### Added
- Added `ControllerNotFoundException` and `ControllerActionNotFoundException` routing exceptions and configurable `enable_not_found_controller` option in `MvcMiddleware`.
- Added unit test suite (`phpunit.xml` and `tests/cases/*`) and GitHub Actions workflow.

### Changed
- Replaced `Action` attribute handling with `ntentan\http\filters\Route` and `RequestFilter` attributes (`Get`, `Post`, `Put`, `Delete`, `Header`, `MimeHeader`) in `MvcMiddleware`.
- Updated `Router` to use centralized `Route::compile` from `ntentan/ntentan`.
- Updated model unserialization in `Model` to reconnect to the database context.
- Updated dependencies.

### Removed
- Removed deprecated `Action` attribute class.

## [v0.6.1] - 2026-08-26

### Changed
- Updated dependencies.

## [v0.6.0] - 2026-08-26

### Added
- Added 404 capability with `NotFoundController` and `NotFoundControllerSpec`.
- Added `loadRecordWrapper` method to `MvcModelFactory` to ensure models are testable.

### Changed
- Switched model creation in `Model` to use the ORM context model factory.
- Adjusted database initialization in `Mvc` to use `DefaultDriverFactory` from Atiaa.

## [v0.5.0] - 2026-06-24

### Added
- Adds support for injecting a logger for database debugging.

## [v0.4.5] - 2026-03-02

### Added
- Added support for custom namespaces in `MvcMiddleware`.

### Changed
- Changes how databases are initialized.
- Updated type hints in `Router`.

### Fixed
- Fixed a bug in `Router` where an incorrect variable was used in an exception message.

## [v0.4.4] - 2026-02-19

### Removed
- Removes the Janitor dependency from the PHP engine factory.

## [v0.4.3] - 2026-01-10

### Fixed
- Fixes an issue with how the template helper is loaded in the MVC environment.

## [v0.4.2] - 2025-12-23

### Changed
- Adapts MVC core to be compatible with the new Ntentan structure.
- Updates the dependencies.

## [v0.4.1] - 2025-09-07

### Changed
- Updates the dependencies.

### Fixed
- Fixes a bug which prevents multiple slashes from being put in a URL.

## [v0.4.0] - 2025-04-06

### Added
- Adding better exception handling for cases where parameters are not found.

## [v0.3.0] - 2025-03-15

### Changed
- The internal references to nibii references were updated to conform with the new `RelationshipType` enumeration.

### Fixed
- Access to the `RequestInterface` was bound to the internal `Request` object.

## [v0.2.0] - 2025-02-02

### Added
- The `MvcCore` class can now accept custom bindings.

## [v0.1.0] - 2025-01-20

### Added
- First release with a Changelog.

