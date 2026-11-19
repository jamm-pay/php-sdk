# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.10.0] - 2026-11-19

### Added

- `V1RefundInfo::getAmountRefundedNetDiscounts()` on refund webhooks — the amount returned to the buyer for this refund (`getAmountRefunded()` minus its share of the charge discount). Sent on `REFUND_SUCCEEDED` only.

## [0.9.0] - 2026-11-06

### Added

- `Jamm\Exception\ApiException` from `Payment::offSession` now has `getStatusCode()` (the HTTP status), `getErrorType()`, `getServerMessage()`, `getDetails()` and `getChargeId()`. `getCode()` and the message are unchanged. `getChargeId()` is the charge ID on an `ERROR_TYPE_PAYMENT_CHARGE_PENDING` answer (outcome not known yet; not returned by the API yet): retry only with the same idempotency key, or wait for the webhook.
- `OffSessionInput::$idempotencyKey` (last constructor argument): an optional key that makes a sync charge retry-safe once it is enabled for your merchant (until then a request with a key is rejected). Not sent when null or blank; unlike `offSessionAsync`, no key is generated for you.
- `ApiException::fromThrowable()`: wrap an error from the generated client (for example `PaymentApi::withdraw`) to read its status, error type and charge ID.

### Fixed

- `Payment::offSession`: on a timeout or another error with no response, the message and `getPrevious()` now carry the transport error instead of an internal `\Error` ("Call to undefined method ConnectException::getResponse()" or "getStatusCode() on null").

### Changed

- PHP 8.1 is now supported. The minimum was 8.2. PHP 8.1 no longer gets security fixes from the PHP project, so upgrade when you can.
- `Payment::offSession` throws `ApiException` for a 2xx answer other than 200 instead of reading it as a payment response. The API doesn't send one.

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
