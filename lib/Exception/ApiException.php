<?php

namespace Jamm\Exception;

/**
 * ApiException represents Jamm API related issues,
 * such as client calls the API with invalid parameters,
 * or Jamm API server is facing trouble.
 */
class ApiException extends \Exception
{
    private const VALUE_DETAIL = 'type.googleapis.com/google.protobuf.Value';
    private const CHARGE_PENDING_DETAIL = 'type.googleapis.com/api.v1.ChargePendingDetail';

    private string $errorType = \OpenAPI\Client\Model\V1ErrorType::ERROR_TYPE_UNSPECIFIED;

    private ?string $serverMessage = null;

    private ?int $statusCode = null;

    /** @var array<int, array<string, mixed>> */
    private array $details = [];

    private ?string $chargeId = null;

    /**
     * Wraps an error from an API call; getCode() stays 0. For a generated client error it reads the HTTP status,
     * error type and details.
     */
    public static function fromThrowable(string $message, \Throwable $e): self
    {
        $text = $message . ': ' . $e->getMessage();
        if (!$e instanceof \OpenAPI\Client\ApiException) {
            return new self($text, 0, $e);
        }

        $ex = new self($text, 0, $e);
        $ex->statusCode = (int) $e->getCode() > 0 ? (int) $e->getCode() : null;
        $body = self::decode($e->getResponseBody());
        $ex->serverMessage = is_string($body['message'] ?? null) ? $body['message'] : null;
        $ex->details = is_array($body['details'] ?? null) ? array_values(array_filter($body['details'], 'is_array')) : [];
        foreach ($ex->details as $d) {
            if (($d['@type'] ?? null) === self::VALUE_DETAIL && is_string($d['value'] ?? null) && str_starts_with($d['value'], 'ERROR_TYPE_')) {
                $ex->errorType = $d['value'];
                break;
            }
        }
        foreach ($ex->details as $d) {
            if (($d['@type'] ?? null) === self::CHARGE_PENDING_DETAIL && is_string($d['chargeId'] ?? null) && $d['chargeId'] !== '') {
                $ex->chargeId = $d['chargeId'];
                break;
            }
        }

        return $ex;
    }

    /**
     * The HTTP status of the API's answer; null when there was none (e.g. a connection timeout).
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * The error type, e.g. ERROR_TYPE_PAYMENT_VALIDATION_FAILED. A type this SDK doesn't know yet is kept as is.
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * The API's own error message, untruncated (getMessage() carries the HTTP client's text).
     */
    public function getServerMessage(): ?string
    {
        return $this->serverMessage;
    }

    /**
     * The error details as returned by the API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * The charge ID on an ERROR_TYPE_PAYMENT_CHARGE_PENDING answer (outcome not known yet).
     * Retry only with the same idempotency key, or wait for the webhook.
     */
    public function getChargeId(): ?string
    {
        return $this->chargeId;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(mixed $body): array
    {
        if (is_object($body)) {
            $body = json_encode($body);
        }
        $decoded = is_string($body) ? json_decode($body, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
