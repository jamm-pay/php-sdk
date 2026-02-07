<?php

namespace Jamm;

use OpenAPI\Client\Api\HealthcheckApi;
use OpenAPI\Client\Model\V1PingResponse;

class Healthcheck
{
    /**
     * Get or create the OpenAPI client
     * @return HealthcheckApi
     * @throws ApiException
     */
    private static function api(): HealthcheckApi
    {
        $client = Config::get()->apiClient();

        return new HealthcheckApi($client->http, $client->conf);
    }

    /**
     * Ping Jamm API to determine health status.
     */
    public function ping(): V1PingResponse
    {
        try {
            return self::api()->ping();
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error pinging healthcheck: {$e->getMessage()}");
        }
    }
}
