<div align="center">
  <a href="https://github.com/jamm-pay/php-sdk">
    <img src="https://assets.jamm-pay.jp/brand/jamm_logo.png" alt="logo" width="120" height="120">
  </a>
  <h3 align="center">Jamm PHP SDK</h3>
  <p align="center">
    The official PHP SDK for Jamm's payment API!
    <br />
    We strongly recommend using the SDK for backend integration in order to simplify and streamline your development process!
  </p>
</div>

## Quickstart

Visit our docs for more information.

<https://docs.jamm-pay.jp>

## Install

```bash
composer require jamm-pay/php-sdk
```

## API version

Every request to the Jamm API carries a `Jamm-API-Version` header. By default it is
the dated API version this SDK was built against, exposed as `Jamm\ApiVersion::VALUE`.
You can pin an older version when initializing the SDK:

```php
Jamm\Config::init('client-id', 'client-secret', 'prod', apiVersion: '2026-08-26');
```

Null or empty means `Jamm\ApiVersion::VALUE`. Otherwise the value must be a `YYYY-MM-DD`
date no later than it; anything else throws a `ConfigException`. To use a newer API version, upgrade the SDK.
The API rejects a version it no longer serves; the error lists the versions it does. Responses to a pinned older version follow that version's shape, so fields added since come back empty in this SDK's types.

Every versioned response echoes what served it in two headers:
`Jamm-API-Version` is the version, and `Jamm-API-Version-Source` is how it was
chosen: `header` (the version this SDK sent), `pin` (your account's pinned
version), `default` (no header, and no pin we could read), or `forced` (Jamm
temporarily served its newest shape, which no dated version describes, so
`Jamm-API-Version` is omitted). A request rejected before a version is chosen
carries neither.

OAuth2 token requests are excluded: they go to the identity service, which is
not versioned.
