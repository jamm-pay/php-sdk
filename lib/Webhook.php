<?php

namespace Jamm;

use Jamm\Config;
use OpenAPI\Client\Model\V1ChargeMessage;
use OpenAPI\Client\Model\V1ContractMessage;
use OpenAPI\Client\Model\V1UserAccountMessage;
use OpenAPI\Client\Model\V1EventType;
use OpenAPI\Client\Model\V1MerchantWebhookMessage;
use OpenAPI\Client\Model\V1RefundInfo;

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
                $out->setContent(new V1ChargeMessage(self::normalizeChargeContent($content)));
                return $out;

            case V1EventType::EVENT_TYPE_CONTRACT_ACTIVATED:
                $out->setContent(new V1ContractMessage(is_array($content) ? $content : []));
                return $out;

            case V1EventType::EVENT_TYPE_USER_ACCOUNT_DELETED:
                $out->setContent(new V1UserAccountMessage(is_array($content) ? $content : []));
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
            return $content;
        }

        $refund = $content['refund'] ?? null;
        $charge = $content['transaction'];

        if (is_array($refund)) {
            $charge['refund'] = new V1RefundInfo($refund);
        }

        return $charge;
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
