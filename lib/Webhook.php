<?php

namespace Jamm;

use Jamm\Config;
use OpenAPI\Client\Model\V1ChargeMessage;
use OpenAPI\Client\Model\V1ContractMessage;
use OpenAPI\Client\Model\V1UserAccountMessage;
use OpenAPI\Client\Model\V1EventType;
use OpenAPI\Client\Model\V1MerchantWebhookMessage;

final class Webhook
{
    /**
     * Parse received webhook payload into a typed message object.
     *
     * @param array<string,mixed> $json  Assoc array decoded from JSON.
     * @throws \RuntimeException
     */
    public static function parse(array $json): V1MerchantWebhookMessage
    {
        $out = new V1MerchantWebhookMessage($json);

        $eventType = $json['event_type'] ?? null;
        $content   = $json['content'] ?? null;

        switch ($eventType) {
            case V1EventType::EVENT_TYPE_CHARGE_CREATED:
            case V1EventType::EVENT_TYPE_CHARGE_UPDATED:
            case V1EventType::EVENT_TYPE_CHARGE_SUCCESS:
            case V1EventType::EVENT_TYPE_CHARGE_FAIL:
            case V1EventType::EVENT_TYPE_REFUND_SUCCEEDED:
            case V1EventType::EVENT_TYPE_REFUND_FAILED:
                $out->setContent(self::buildModel(V1ChargeMessage::class, self::normalizeChargeContent($content)));
                return $out;

            case V1EventType::EVENT_TYPE_CONTRACT_ACTIVATED:
                $out->setContent(self::buildModel(V1ContractMessage::class, is_array($content) ? $content : []));
                return $out;

            case V1EventType::EVENT_TYPE_USER_ACCOUNT_DELETED:
                $out->setContent(self::buildModel(V1UserAccountMessage::class, is_array($content) ? $content : []));
                return $out;
        }

        throw new \Jamm\Exception\UnknownEventTypeException('Unknown event type');
    }

    /**
     * Normalize charge/refund webhook content into the flat V1ChargeMessage shape.
     *
     * Newer refund webhooks nest the charge under `transaction` and the refund
     * details under `refund` (e.g. `{ "transaction": {...}, "refund": {...} }`).
     * Older payloads send the transaction fields flat. This flattens the former
     * so V1ChargeMessage exposes `id`, `customer`, etc. either way.
     *
     * @param mixed $content
     * @return array<string,mixed>
     */
    private static function normalizeChargeContent($content): array
    {
        if (!is_array($content)) {
            return [];
        }

        if (!isset($content['transaction']) || !is_array($content['transaction'])) {
            // Flat charge payload; buildModel() handles enum coercion.
            return $content;
        }

        $refund = $content['refund'] ?? null;
        $charge = $content['transaction'];

        if (is_array($refund)) {
            // Keep refund as the raw array; buildModel() coerces it into a typed
            // V1RefundInfo (and recursively types its nested error). Also surface
            // the rfd- id on the flat refund_id attribute the model documents.
            $charge['refund'] = $refund;
            if (isset($refund['id'])) {
                $charge['refund_id'] = $refund['id'];
            }
        }

        return $charge;
    }

    /**
     * Construct a generated model from a snake_case webhook payload, coercing
     * wire values into the shapes the model expects:
     *   - numeric enums  -> their string enum constant (the backend serializes
     *                       webhooks with Go's json.Marshal, so enums arrive
     *                       numeric while the generated models are string-based)
     *   - nested models  -> typed instances (the generated constructor assigns
     *                       nested arrays verbatim, so e.g. refund.error would
     *                       stay a raw array and getError()->getCode() would fatal)
     *   - "Type[]" lists -> each element coerced by Type
     * Unknown keys are ignored by the generated constructor (forward-compatible).
     *
     * @param class-string $class
     * @param array<string,mixed> $data
     */
    private static function buildModel(string $class, array $data): object
    {
        $types = $class::openAPITypes();

        foreach ($data as $key => $value) {
            $type = $types[$key] ?? null;
            if ($type !== null) {
                $data[$key] = self::coerceValue($type, $value);
            }
        }

        return new $class($data);
    }

    /**
     * Coerce a single wire value according to its openapi type string.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function coerceValue(string $type, $value)
    {
        // "Type[]" array fields: coerce each element by the inner type.
        if (is_array($value) && str_ends_with($type, '[]')) {
            $inner = substr($type, 0, -2);
            return array_map(static fn($element) => self::coerceValue($inner, $element), $value);
        }

        $class = ltrim($type, '\\');

        // Numeric enum -> string constant. Unknown/out-of-range values fall back
        // to the enum's first member (the *_UNSPECIFIED zero value).
        if (is_int($value) && class_exists($class) && method_exists($class, 'getAllowableEnumValues')) {
            $values = $class::getAllowableEnumValues();
            return $values[$value] ?? $values[0];
        }

        // Nested model -> typed instance.
        if (is_array($value) && class_exists($class) && method_exists($class, 'openAPITypes')) {
            return self::buildModel($class, $value);
        }

        return $value;
    }

    /**
     * Verify webhook signature.
     *
     * @param array<string,mixed> $data
     * @throws \InvalidArgumentException
     * @throws InvalidSignatureException
     */
    public static function verify(array $data, string $signature): void
    {
        // In Ruby they accept nil; in PHP this signature already prevents null,
        // but we keep checks for parity / clearer errors.
        if ($data === []) {
            // If you want Ruby-parity strictly, remove this and allow empty array.
            // Leaving it permissive is usually better; feel free to delete this check.
        }
        if ($signature === '') {
            throw new \Jamm\Exception\InvalidArgumentException('signature cannot be empty');
        }

        // Convert payload to JSON string (Ruby: JSON.dump).
        // JSON_UNESCAPED_UNICODE is required so non-ASCII characters (e.g. Japanese)
        // are serialized as raw UTF-8 bytes, matching the signing format on the server.
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \Jamm\Exception\InvalidArgumentException('failed to encode data as JSON');
        }

        $config = Config::get();
        $digest = hash_hmac('sha256', $json, $config->clientSecret);
        $given  = "sha256={$digest}";

        if (self::secureCompare($given, $signature)) {
            return;
        }

        throw new \Jamm\Exception\InvalidSignatureException('Digests do not match');
    }

    /**
     * Constant-time string comparison.
     */
    public static function secureCompare(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) {
            return false;
        }

        // XOR each byte and accumulate the result
        $result = 0;
        $len = strlen($a);

        for ($i = 0; $i < $len; $i++) {
            $result |= (ord($a[$i]) ^ ord($b[$i]));
        }

        return $result === 0;
    }
}
