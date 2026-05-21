# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-05-20

### Added

- Added `Jamm\Payment::offSessionAsync` for async off-session charges
- Auto-fill `idempotency_key` with a UUID v4 when null, empty, or whitespace
- Raise `ApiException` when the server returns `GooglerpcStatus`

## [0.3.0] - 2026-04-03

### Added

- Added `ChargeError` details on `ChargeResult` for failed charges

## [0.2.0] - 2026-03-18

### Added

- Platform identity
- Payment refund feature

### Changed

- Switched offSession payments to behave asynchronously

## [0.1.0] - 2026-02-06

### Added

- Implemented first SDK version
