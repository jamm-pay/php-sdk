<?php

namespace Jamm;

use Jamm\Request;
use OpenAPI\Client\Api\PaymentApi;
use OpenAPI\Client\Model\V1GetChargeResponse;
use OpenAPI\Client\Model\V1GetChargesResponse;
use OpenAPI\Client\Model\V1OnSessionPaymentRequest;
use OpenAPI\Client\Model\V1OnSessionPaymentResponse;
use OpenAPI\Client\Model\V1OffSessionPaymentRequest;
use OpenAPI\Client\Model\V1OffSessionPaymentResponse;

class Payment
{
    /**
     * Get or create the OpenAPI client
     * @return PaymentApi
     * @throws ApiException
     */
    private static function api(): PaymentApi
    {
        $client = Config::get()->apiClient();

        return new PaymentApi($client->http, $client->conf);
    }

    /**
     * On Session Payment
     *
     * Provides a unified interface for creating payment sessions.
     * This API intelligently routes requests to the appropriate payment strategy.
     */
    public function onSession(
        ?string $customer = null,
        ?bool $one_time = false,
        ?Request\Buyer $buyer = null,
        ?Request\InitialCharge $charge = null,
        ?Request\URL $redirect = null,
    ): V1OnSessionPaymentResponse {
        $r = new V1OnSessionPaymentRequest();

        $r->setOneTime($one_time);

        if ($customer !== null) {
            $r->setCustomer($customer);
        }
        if ($buyer !== null) {
            $r->setBuyer($buyer);
        }
        if ($charge !== null) {
            $r->setCharge($charge);
        }
        if ($redirect !== null) {
            $r->setRedirect($redirect);
        }

        try {
            return self::api()->onSessionPayment($r);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException($e);
        }
    }

    /**
     * Off Session Payment
     *
     * Charge customer in synchronous request.
     * The customer must be already created, and he/she must complete Jamm onboarding
     * including terms of service acceptance, KYC, and payment method setup.
     */
    public function offSession(
        string $customer,
        Request\InitialCharge $charge,
    ): V1OffSessionPaymentResponse {
        $r = new V1OffSessionPaymentRequest();

        $r->setCustomer($customer);
        $r->setCharge($charge);

        try {
            return self::api()->offSessionPayment($r);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException($e);
        }
    }

    /**
     * Get a charge by ID.
     */
    public function getCharge(string $id): V1GetChargeResponse
    {
        try {
            return self::api()->getCharge($id);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException($e);
        }
    }

    /**
     * Get charges by ID.
     */
    public function getCharges(string $customer = '', ?Request\Pagination $pagination = null): V1GetChargesResponse
    {
        $page_size = null;
        $page_token = null;

        if ($pagination !== null) {
            $page_size = $pagination->getPageSize();
            $page_token = $pagination->getPageToken();
        }

        try {
            return self::api()->getCharges($customer, $page_size, $page_token);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException($e);
        }
    }
}
