# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.0] - 2026-10-05

### Added

- Every request to the Jamm API (not OAuth token requests) sends a `Jamm-API-Version` header defaulting to the API version this SDK was built for (`Jamm\ApiVersion::VALUE`). To stay on an older version, pass `apiVersion` to `Config::init` or `new Client(...)`; a newer one needs an SDK upgrade.
- New error type `ERROR_TYPE_PAYMENT_CHARGE_OVER_DAILY_LIMIT`: the charge would exceed the daily limit of the buyer's bank account. Retry after midnight JST.
- Charge webhooks expose `getMetadata()`, the map you set when creating the charge, on every charge and refund event. It is `null` when the charge has none.

### Changed

- The SDK version header is renamed from `X-SDK-Version` to `Jamm-SDK-Version`. If a proxy or firewall allowlists outgoing headers, add `Jamm-SDK-Version`.
- Webhook parsing accepts both `snake_case` and `camelCase` field names, so a future change to the webhook format won't break it.

### Deprecated

- `ERROR_TYPE_CSV_DUPLICATE_USER` is no longer returned.

## [0.7.0] - 2026-07-15

### Added

- Added `Webhook::verifyAndParse(string $rawBody)` — verifies the HMAC signature over the exact received `content` bytes and parses in one step. Unlike `Webhook::verify`, it is not broken by JSON re-serialization (correctly handles `&`, `<`, `>` in content) and rejects bodies with duplicate top-level keys.
- Resolve numeric enum wire values (`status`, `api_source`, …) onto their string enum constants on parsed charge/refund webhooks, matching REST API responses (the backend serializes webhooks with `json.Marshal`, so all enums arrive numeric)
- Surface the refund `rfd-` id on the flat `refund_id` attribute in addition to the nested `refund`

### Fixed

- `status` on refund/charge webhooks is no longer left as a raw integer
- Nested webhook fields (e.g. `refund.error`) are now typed model instances instead of raw arrays, so `getError()->getCode()` / `getMessage()` work instead of a fatal error

## [0.6.0] - 2026-07-03

(Not Available)

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
