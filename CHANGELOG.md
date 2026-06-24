# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 2026-06-24

### Added

- Resolve numeric enum wire values (`status`, `api_source`, …) onto their string enum constants on parsed charge/refund webhooks, matching REST API responses (the backend serializes webhooks with `json.Marshal`, so all enums arrive numeric)
- Surface the refund `rfd-` id on the flat `refund_id` attribute in addition to the nested `refund`

### Fixed

- `status` on refund/charge webhooks is no longer left as a raw integer
- Nested webhook fields (e.g. `refund.error`) are now typed model instances instead of raw arrays, so `getError()->getCode()` / `getMessage()` work instead of a fatal error

## [0.5.0] - 2026-06-17

### Added

- `Webhook::parse` now handles the nested refund webhook format (`content.transaction` + `content.refund`), exposing transaction fields and a typed `V1RefundInfo` on the parsed `V1ChargeMessage`

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
