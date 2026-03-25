<?php

namespace Jamm;

class Environment
{
    /**
     * Returns environment variables related to Jamm SDK
     * Mainly for internal testing.
     */
    public static function get(): \stdClass
    {
        $out = new \stdClass();

        $out->client_id = getenv('MERCHANT_CLIENT_ID') ?: getenv('JAMM_CLIENT_ID');
        $out->client_secret = getenv('MERCHANT_CLIENT_SECRET') ?: getenv('JAMM_CLIENT_SECRET');
        $out->platform_client_id = getenv('PLATFORM_CLIENT_ID');
        $out->platform_client_secret = getenv('PLATFORM_CLIENT_SECRET');
        $out->environment = getenv('JAMM_ENVIRONMENT');

        return $out;
    }
}
