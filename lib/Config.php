<?php

namespace Jamm;

use OpenAPI\Client\Configuration;
use GuzzleHttp\Client as GuzzleClient;
use Jamm\Exception\ConfigException;

final class Config
{
    public const ENV_LOCAL = 'local';
    public const ENV_STAGING = 'staging';
    public const ENV_PROD  = 'prod';

    /**
     * SDK operation modes.
     * - PLATFORM: Allows calling Jamm API on behalf of merchants (requires platform credentials)
     * - MERCHANT: Standard merchant mode for direct API access
     */
    public const MODE_PLATFORM = 'platform';
    public const MODE_MERCHANT = 'merchant';

    /**
     * SDK version, updated through build pipeline.
     */
    public const VERSION = '0.6.0';

    private static ?self $instance = null;

    /**
     * Cached API client context to avoid re-instantiating Guzzle.
     * Not used when a merchant ID is provided (per-request client).
     */
    private ?ApiClientContext $apiClientContext = null;

    private function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $environment,
        public readonly string $apiBaseUrl,
        public readonly string $oauth2BaseUrl,
        public readonly string $mode,
    ) {}

    /**
     * Initializes the Singleton instance.
     *
     * @example Merchant mode
     * ```php
     * Config::init('client-id', 'client-secret', 'prod');
     * ```
     *
     * @example Platform mode (call API on behalf of merchants)
     * ```php
     * Config::init('platform-client-id', 'platform-client-secret', 'prod', platform: true);
     * ```
     */
    public static function init(
        string $clientId,
        string $clientSecret,
        string $environment = self::ENV_PROD,
        bool $platform = false,
    ): self {
        // Validation: Ensure we don't have empty credentials
        if (empty($clientId) || empty($clientSecret)) {
            throw new ConfigException('Client ID and Secret are required.');
        }

        $mode = $platform ? self::MODE_PLATFORM : self::MODE_MERCHANT;

        // Clear cached OAuth token so new credentials take effect immediately
        OAuth::clear();

        self::$instance = new self(
            clientId: $clientId,
            clientSecret: $clientSecret,
            environment: $environment,
            apiBaseUrl: self::getApiUrl($environment),
            oauth2BaseUrl: self::getOAuth2Url($environment, $mode),
            mode: $mode,
        );

        return self::$instance;
    }

    /**
     * Retrieves the initialized instance.
     */
    public static function get(): self
    {
        return self::$instance ?? throw new ConfigException('Jamm SDK not initialized. Call Config::init() first.');
    }

    public static function reset(): void
    {
        self::$instance = null;
        OAuth::clear();
    }

    public function verifySSL(): bool
    {
        return $this->environment !== self::ENV_LOCAL;
    }

    /**
     * Returns the API context containing Configuration and the HTTP Client.
     *
     * In platform mode, pass a merchant ID to call the API on behalf of that merchant.
     * The merchant ID is validated against the format `mer-[alphanumeric/underscore/hyphen]`.
     *
     * @param string|null $merchant Optional merchant ID for platform mode (format: mer-*)
     * @throws \Jamm\Exception\ConfigException
     */
    public function apiClient(?string $merchant = null): ApiClientContext
    {
        if ($merchant !== null) {
            if ($this->mode !== self::MODE_PLATFORM) {
                throw new \Jamm\Exception\ConfigException('merchant parameter can only be used when mode is PLATFORM');
            }
            if (!preg_match('/^mer-[0-9A-Za-z_-]+$/', $merchant)) {
                throw new \Jamm\Exception\ConfigException('invalid merchant id format');
            }
        }

        // When a merchant ID is provided, build a one-off client (not cached)
        if ($merchant !== null) {
            return $this->buildApiClientContext($merchant);
        }

        if ($this->apiClientContext !== null) {
            return $this->apiClientContext;
        }

        return $this->apiClientContext = $this->buildApiClientContext(null);
    }

    private function buildApiClientContext(?string $merchant): ApiClientContext
    {
        $token = OAuth::getAccessToken();

        $conf = new Configuration();
        $conf->setHost($this->apiBaseUrl);

        $headers = [
            'X-SDK-Version' => 'php:' . self::VERSION,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        if ($merchant !== null) {
            $headers['Jamm-Merchant'] = $merchant;
        }

        $http = new GuzzleClient([
            'headers' => $headers,
            'verify'  => $this->verifySSL(),
            'timeout' => 30.0,
        ]);

        return new ApiClientContext($conf, $http);
    }

    private static function getApiUrl(string $environment): string
    {
        return match ($environment) {
            self::ENV_PROD  => 'https://api.jamm-pay.jp',
            self::ENV_LOCAL => 'https://api.jamm.test',
            default         => "https://api.{$environment}.jamm-pay.jp",
        };
    }

    private static function getOAuth2Url(string $environment, string $mode = self::MODE_MERCHANT): string
    {
        $prefix = $mode === self::MODE_PLATFORM ? 'platform-identity' : 'merchant-identity';

        return match ($environment) {
            self::ENV_PROD  => "https://{$prefix}.jamm-pay.jp",
            self::ENV_LOCAL => "https://{$prefix}.develop.jamm-pay.jp",
            default         => "https://{$prefix}.{$environment}.jamm-pay.jp",
        };
    }
}

/**
 * Data class to hold the OpenAPI configuration and the Guzzle client.
 */
final class ApiClientContext
{
    public function __construct(
        public readonly Configuration $conf,
        public readonly GuzzleClient $http,
    ) {}
}
