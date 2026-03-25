<?php

namespace Jamm;

use Jamm\Customer;
use Jamm\Healthcheck;
use Jamm\Payment;
use Jamm\Webhook;

/**
 * Main Jamm SDK Client
 *
 * Usage:
 * $jamm = new \Jamm\Client(
 *     clientId: 'your-client-id',
 *     clientSecret: 'your-client-secret',
 *     environment: 'prod', // or 'staging' or 'local'
 * );
 *
 * $buyer = new \Jamm\Request\Buyer([
 *     'name' => 'Jenny Rosen',
 *     'email' => 'jennyrosen@example.com',
 * ]);
 * $customer = $jamm->customer->create($buyer);
 */
class Client
{
    public Customer $customer;
    public Healthcheck $healthcheck;
    public Payment $payment;
    public Webhook $webhook;

    /**
     * Initialize Jamm SDK
     *
     * @param string $clientId Client ID for OAuth2 authentication
     * @param string $clientSecret Client secret for OAuth2 authentication
     * @param string $environment Environment (prod, staging, local)
     * @param bool $platform Set to true for platform mode (call API on behalf of merchants)
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        string $environment = Config::ENV_PROD,
        bool $platform = false,
    ) {
        // Initialize the global config
        Config::init($clientId, $clientSecret, $environment, $platform);

        // Initialize services
        $this->customer = new Customer();
        $this->healthcheck = new Healthcheck();
        $this->payment = new Payment();
        $this->webhook = new Webhook();
    }
}
