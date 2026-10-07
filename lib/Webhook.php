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
        // Read before any model is built, so it needs the same treatment.
        $json = self::withAttributeNames(V1MerchantWebhookMessage::class, $json);
        $out  = new V1MerchantWebhookMessage($json);

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
        $data  = self::withAttributeNames($class, $data);

        foreach ($data as $key => $value) {
            $type = $types[$key] ?? null;
            if ($type !== null) {
                $data[$key] = self::coerceValue($type, $value);
            }
        }

        return new $class($data);
    }

    /**
     * attributeMap is ['snake_name' => 'camelName']; accept either spelling.
     *
     * @param class-string $class
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function withAttributeNames(string $class, array $data): array
    {
        // Memoised: otherwise flipped for every nested model, every webhook.
        static $bySerializedByClass = [];
        $bySerialized = $bySerializedByClass[$class] ??= array_flip($class::attributeMap());

        $out = [];
        foreach ($data as $key => $value) {
            $out[$bySerialized[$key] ?? $key] = $value;
        }

        return $out;
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
     * Verify the HMAC signature over the exact received bytes and parse, in one step.
     *
     * This is the recommended entry point. The backend signs the raw `content` bytes it
     * transmits, produced by Go's JSON encoder which HTML-escapes & < > as &amp; &lt;
     * &gt;. Re-serializing the parsed content (as verify() does via json_encode) does not
     * reproduce those escapes, so its digest no longer matches. This slices the raw
     * `content` substring out of $rawBody verbatim and HMACs that.
     *
     * @throws \Jamm\Exception\InvalidArgumentException
     * @throws \Jamm\Exception\InvalidSignatureException
     */
    public static function verifyAndParse(string $rawBody): V1MerchantWebhookMessage
    {
        if ($rawBody === '') {
            throw new \Jamm\Exception\InvalidArgumentException('rawBody cannot be empty');
        }

        $parsed = json_decode($rawBody, true);
        if (!is_array($parsed)) {
            throw new \Jamm\Exception\InvalidArgumentException('Invalid webhook JSON');
        }

        $signature = $parsed['signature'] ?? '';
        if (!is_string($signature) || $signature === '') {
            throw new \Jamm\Exception\InvalidArgumentException("Webhook body is missing the 'signature' field");
        }

        $rawContent = self::extractRawContent($rawBody);
        $config = Config::get();
        $digest = hash_hmac('sha256', $rawContent, $config->clientSecret);
        $given  = "sha256={$digest}";

        if (!self::secureCompare($given, $signature)) {
            throw new \Jamm\Exception\InvalidSignatureException('Digests do not match');
        }

        return self::parse($parsed);
    }

    /**
     * Extract the top-level `content` value substring from a raw webhook body verbatim,
     * without decoding or re-serializing it, so the exact signed bytes are recovered.
     */
    private static function extractRawContent(string $rawBody): string
    {
        $n = strlen($rawBody);
        $i = 0;
        while ($i < $n && ctype_space($rawBody[$i])) {
            $i++;
        }
        if ($i >= $n || $rawBody[$i] !== '{') {
            throw new \Jamm\Exception\InvalidArgumentException('Webhook body must be a JSON object');
        }
        $i++; // consume '{'

        // Scan every top-level key. Duplicate keys are rejected: json_decode keeps the LAST
        // occurrence while this returns the FIRST, so a duplicate `content` could otherwise
        // verify one payload and parse another (signature bypass).
        $seen = [];
        $content = null;
        while (true) {
            while ($i < $n && (ctype_space($rawBody[$i]) || $rawBody[$i] === ',')) {
                $i++;
            }
            if ($i >= $n || $rawBody[$i] === '}') {
                break;
            }
            if ($rawBody[$i] !== '"') {
                throw new \Jamm\Exception\InvalidArgumentException('Malformed webhook JSON');
            }
            $keyStart = $i;
            $i = self::skipString($rawBody, $i);
            // Decode the key (not a raw slice): otherwise an escaped duplicate such as
            // "content" would evade both the duplicate check and the 'content' match below,
            // while json_decode() collapses it to `content` and keeps the last value.
            $key = json_decode(substr($rawBody, $keyStart, $i - $keyStart));
            if (!is_string($key)) {
                throw new \Jamm\Exception\InvalidArgumentException('Malformed webhook JSON');
            }
            if (isset($seen[$key])) {
                throw new \Jamm\Exception\InvalidArgumentException("Duplicate top-level key in webhook body: {$key}");
            }
            $seen[$key] = true;
            while ($i < $n && ctype_space($rawBody[$i])) {
                $i++;
            }
            if ($i >= $n || $rawBody[$i] !== ':') {
                throw new \Jamm\Exception\InvalidArgumentException('Malformed webhook JSON');
            }
            $i++; // consume ':'
            while ($i < $n && ctype_space($rawBody[$i])) {
                $i++;
            }
            $valueStart = $i;
            $i = self::skipValue($rawBody, $i);
            if ($key === 'content') {
                $content = substr($rawBody, $valueStart, $i - $valueStart);
            }
        }

        if ($content === null) {
            throw new \Jamm\Exception\InvalidArgumentException("Webhook body does not contain 'content' field");
        }
        return $content;
    }

    /**
     * $i points at an opening '"'. Returns the index just past the closing '"'.
     */
    private static function skipString(string $s, int $i): int
    {
        $i++; // opening quote
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if ($c === '\\') {
                $i += 2;
                continue;
            }
            if ($c === '"') {
                return $i + 1;
            }
            $i++;
        }
        throw new \Jamm\Exception\InvalidArgumentException('Unterminated string in webhook JSON');
    }

    /**
     * $i points at the first char of a JSON value. Returns the index just past it.
     */
    private static function skipValue(string $s, int $i): int
    {
        if ($s[$i] === '"') {
            return self::skipString($s, $i);
        }
        if ($s[$i] === '{' || $s[$i] === '[') {
            $depth = 0;
            $n = strlen($s);
            while ($i < $n) {
                $ch = $s[$i];
                if ($ch === '"') {
                    $i = self::skipString($s, $i);
                    continue;
                }
                if ($ch === '{' || $ch === '[') {
                    $depth++;
                } elseif ($ch === '}' || $ch === ']') {
                    $depth--;
                    if ($depth === 0) {
                        return $i + 1;
                    }
                }
                $i++;
            }
            throw new \Jamm\Exception\InvalidArgumentException('Unterminated object/array in webhook JSON');
        }
        $n = strlen($s);
        while ($i < $n && strpos(",}] \t\n\r", $s[$i]) === false) {
            $i++;
        }
        return $i;
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
