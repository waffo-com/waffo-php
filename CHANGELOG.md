# Changelog

## [0.4.0] - 2026-10-21

### Added

- `onEvent(string, callable)` explicitly registers custom webhook names and receives the complete verified notification. Unknown unregistered events still fail. Built-in names use dedicated registration methods; invalid callable types retain PHP's native `TypeError`.

## [0.3.0] - 2026-09-04

### Added

- Added typed `topupInfo` request and response models to `subscription()->update()` for charging a current-period add-on while preserving the subscription billing schedule.

## [0.2.0] - 2026-08-27

### Changed

- Lower the minimum runtime to PHP 8.0 while keeping one Composer package for PHP 8.0 through PHP 8.5.
- Preserve documented environment constants and backed-value helpers through PHP 8.0-compatible value objects.
- Extend CI and package smoke coverage to the minimum and current PHP runtimes.

### Compatibility note

- `Environment` and `WebhookEventType` are value objects rather than native `BackedEnum` implementations so one source package remains parseable on PHP 8.0. Constant-based SDK configuration and the documented value helpers are preserved; enum reflection, `instanceof BackedEnum`, and direct enum-case property access on constants are not.
- Types that used PHP 8.1 `readonly` syntax are mutable at the language level on the PHP 8.0-compatible codebase. Consumers should continue to treat SDK configuration and value objects as immutable after construction.

## [0.1.0] - 2026-08-22

### Added

- Initial PHP SDK beta with order, refund, subscription, merchant configuration, payment-method configuration, wallet, and webhook capabilities.
- OpenAPI-generated DTOs, shared vector tests, README executable examples, package validation, and real sandbox E2E archive support.
