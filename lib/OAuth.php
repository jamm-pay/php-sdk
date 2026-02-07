<?php

namespace Jamm;

class OAuth
{
    private static ?array $token = null;

    /**
     * Fetches an OAuth2 access token from the Jamm Identity Provider.
     * The token retrieval process is embedded in each services, therefore
     * developer does not need to call this function directly.
     *
     * @return string OAuth2 access token
     * @throws Exception
     */
    public static function getAccessToken(): string
    {
        if (self::$token !== null) {
            // 30 seconds buffer
            $now = time() - 30;
            if (self::$token['expires_at'] > $now) {
                return self::$token['access_token'];
            }
        }

        $config = Config::get();

        $basicAuth = base64_encode($config->clientId . ':' . $config->clientSecret);

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $config->clientId,
        ]);

        $ch = curl_init($config->oauth2BaseUrl . '/oauth2/token');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $basicAuth,
            ],
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            throw new \Jamm\Exception\OAuthException("Error fetching token: {$error}");
        }

        if ($response === false) {
            throw new \Jamm\Exception\OAuthException("Error fetching token: empty or invalid response from server");
        }
        $data = json_decode($response, true);

        if ($statusCode < 200 || $statusCode >= 400) {
            $errorMessage = json_encode($data);
            throw new \Jamm\Exception\OAuthException("Error fetching token, error code: {$statusCode}, error message: {$errorMessage}");
        }

        // Store with expiration timestamp
        self::$token = [
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'],
            'expires_in' => $data['expires_in'],
            'expires_at' => time() + $data['expires_in'],
        ];

        return self::$token['access_token'];
    }

    /**
     * Clear the token cache
     */
    public static function clear(): void
    {
        self::$token = null;
    }
}
