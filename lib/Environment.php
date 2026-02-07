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

        $out->client_id = getenv('JAMM_CLIENT_ID');
        $out->client_secret = getenv('JAMM_CLIENT_SECRET');
        $out->environment = getenv('JAMM_ENVIRONMENT');

        return $out;
    }
}
