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
            case V1EventType::EVENT_TYPE_CHARGE_CANCEL:
            case V1EventType::EVENT_TYPE_CHARGE_SUCCESS:
            case V1EventType::EVENT_TYPE_CHARGE_FAIL:
                $out->setContent(new V1ChargeMessage(is_array($content) ? $content : []));
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

        // Convert payload to JSON string (Ruby: JSON.dump)
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
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
