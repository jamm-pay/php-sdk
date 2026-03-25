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
     * Get or create the OpenAPI client.
     * In platform mode, pass a merchant ID to call the API on behalf of that merchant.
     *
     * @param string|null $merchant Optional merchant ID for platform mode (format: mer-*)
     * @return PaymentApi
     */
    private static function api(?string $merchant = null): PaymentApi
    {
        $client = Config::get()->apiClient($merchant);

        return new PaymentApi($client->http, $client->conf);
    }

    /**
     * On Session Payment
     *
     * Provides a unified interface for creating payment sessions.
     * This API intelligently routes requests to the appropriate payment strategy.
     *
     * @example Merchant mode
     * ```php
     * $input = new \Jamm\Request\OnSessionInput(buyer: $buyer, charge: $charge, redirect: $redirect);
     * $jamm->payment->onSession($input);
     * ```
     *
     * @example Platform mode
     * ```php
     * $input = new \Jamm\Request\OnSessionInput(buyer: $buyer, charge: $charge, redirect: $redirect, merchant: 'mer-merchant-123');
     * $jamm->payment->onSession($input);
     * ```
     *
     * @param Request\OnSessionInput $input
     */
    public function onSession(Request\OnSessionInput $input): V1OnSessionPaymentResponse
    {
        $r = new V1OnSessionPaymentRequest();

        $r->setOneTime($input->oneTime);

        if ($input->customer !== null) {
            $r->setCustomer($input->customer);
        }
        if ($input->buyer !== null) {
            $r->setBuyer($input->buyer);
        }
        if ($input->charge !== null) {
            $r->setCharge($input->charge);
        }
        if ($input->redirect !== null) {
            $r->setRedirect($input->redirect);
        }

        try {
            return self::api($input->merchant)->onSessionPayment($r);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error creating on-session payment: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Off Session Payment
     *
     * Charge customer in synchronous request without customer interaction.
     * The customer must already exist and have completed Jamm onboarding,
     * including terms of service acceptance, KYC, and payment method setup.
     *
     * @example Merchant mode
     * ```php
     * $input = new \Jamm\Request\OffSessionInput('cus-123', $charge);
     * $jamm->payment->offSession($input);
     * ```
     *
     * @example Platform mode
     * ```php
     * $input = new \Jamm\Request\OffSessionInput('cus-123', $charge, merchant: 'mer-merchant-123');
     * $jamm->payment->offSession($input);
     * ```
     *
     * @param Request\OffSessionInput $input
     */
    public function offSession(Request\OffSessionInput $input): V1OffSessionPaymentResponse
    {
        $r = new V1OffSessionPaymentRequest();

        $r->setCustomer($input->customer);
        $r->setCharge($input->charge);

        try {
            return self::api($input->merchant)->offSessionPaymentAsync($r)->wait();
        } catch (\Throwable $e) {
            throw new \Jamm\Exception\ApiException("Error creating off-session payment: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get a charge by ID.
     *
     * @example Merchant mode
     * ```php
     * $jamm->payment->getCharge('chg-123');
     * ```
     *
     * @example Platform mode
     * ```php
     * $jamm->payment->getCharge('chg-123', merchant: 'mer-merchant-123');
     * ```
     *
     * @param string|null $merchant Optional merchant ID for platform mode
     */
    public function getCharge(string $id, ?string $merchant = null): V1GetChargeResponse
    {
        try {
            return self::api($merchant)->getCharge($id);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error getting charge: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get charges for a customer. The response is paginated.
     *
     * @example Merchant mode
     * ```php
     * $input = new \Jamm\Request\GetChargesInput('cus-123');
     * $jamm->payment->getCharges($input);
     * ```
     *
     * @example Platform mode
     * ```php
     * $input = new \Jamm\Request\GetChargesInput('cus-123', merchant: 'mer-merchant-123');
     * $jamm->payment->getCharges($input);
     * ```
     *
     * @param Request\GetChargesInput $input
     */
    public function getCharges(Request\GetChargesInput $input): V1GetChargesResponse
    {
        $page_size = null;
        $page_token = null;

        if ($input->pagination !== null) {
            $page_size = $input->pagination->getPageSize();
            $page_token = $input->pagination->getPageToken();
        }

        try {
            return self::api($input->merchant)->getCharges($input->customer, $page_size, $page_token);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error getting charges: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Refund a charge.
     *
     * The refund is always processed asynchronously. The final result is
     * delivered via webhook (`charge_refund`).
     *
     * If the same-day cancellation window has not passed, cancels the charge
     * directly. Otherwise, creates a bank transfer refund request.
     *
     * @example Merchant mode
     * ```php
     * $jamm->payment->refund('chg-123');
     * $jamm->payment->refund('chg-123', amount: 1000);
     * $jamm->payment->refund('chg-123', cancelOnly: true);
     * ```
     *
     * @example Platform mode
     * ```php
     * $jamm->payment->refund('chg-123', merchant: 'mer-merchant-123');
     * ```
     *
     * @param string $chargeId The charge ID to refund
     * @param int|null $amount Optional refund amount in JPY. If omitted, the full refundable amount is used.
     * @param bool $cancelOnly When true, only attempts cancellation without falling back to bank transfer refund.
     * @param string|null $merchant Optional merchant ID for platform mode
     * @return array{chargeId: string, refundId: string}
     */
    public function refund(
        string $chargeId,
        ?int $amount = null,
        bool $cancelOnly = false,
        ?string $merchant = null,
    ): array {
        $conf = Config::get();
        $client = $conf->apiClient($merchant);

        $body = ['chargeId' => $chargeId];

        if ($amount !== null) {
            $body['amount'] = $amount;
        }
        if ($cancelOnly) {
            $body['cancelOnly'] = true;
        }

        try {
            $response = $client->http->post($conf->apiBaseUrl . '/v1/refund', [
                'json' => $body,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (!is_array($data) || !isset($data['chargeId'], $data['refundId'])) {
                throw new \Jamm\Exception\ApiException(
                    'Unexpected refund response: ' . (string) $response->getBody()
                );
            }

            return [
                'chargeId' => $data['chargeId'],
                'refundId' => $data['refundId'],
            ];
        } catch (\Jamm\Exception\ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error refunding charge: {$e->getMessage()}", 0, $e);
        }
    }
}
