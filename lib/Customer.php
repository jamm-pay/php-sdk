<?php

namespace Jamm;

use Jamm\Request;
use OpenAPI\Client\Api\CustomerApi;
use OpenAPI\Client\Model\V1CreateCustomerRequest;
use OpenAPI\Client\Model\V1CreateCustomerResponse;
use OpenAPI\Client\Model\V1Customer;
use OpenAPI\Client\Model\V1DeleteCustomerResponse;
use OpenAPI\Client\Model\V1UpdateCustomerResponse;

class Customer
{
    /**
     * Get or create the OpenAPI client.
     * In platform mode, pass a merchant ID to call the API on behalf of that merchant.
     *
     * @param string|null $merchant Optional merchant ID for platform mode (format: mer-*)
     * @return CustomerApi
     */
    private static function api(?string $merchant = null): CustomerApi
    {
        $client = Config::get()->apiClient($merchant);

        return new CustomerApi($client->http, $client->conf);
    }

    /**
     * Create a new customer.
     *
     * @example Merchant mode
     * ```php
     * $input = new \Jamm\Request\CreateCustomerInput($buyer);
     * $jamm->customer->create($input);
     * ```
     *
     * @example Platform mode
     * ```php
     * $input = new \Jamm\Request\CreateCustomerInput($buyer, merchant: 'mer-merchant-123');
     * $jamm->customer->create($input);
     * ```
     *
     * @param Request\CreateCustomerInput $input
     * @return V1CreateCustomerResponse
     */
    public function create(Request\CreateCustomerInput $input): V1CreateCustomerResponse
    {
        $request = new V1CreateCustomerRequest();
        $request->setBuyer($input->buyer);

        try {
            return self::api($input->merchant)->create($request);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error creating customer: {$e->getMessage()}");
        }
    }

    /**
     * Get a customer.
     *
     * @example Merchant mode
     * ```php
     * $jamm->customer->get('cus-123');
     * ```
     *
     * @example Platform mode
     * ```php
     * $jamm->customer->get('cus-123', merchant: 'mer-merchant-123');
     * ```
     *
     * @param string $customerId
     * @param string|null $merchant Optional merchant ID for platform mode
     * @return V1Customer|null
     */
    public function get(string $customerId, ?string $merchant = null): ?V1Customer
    {
        try {
            $got = self::api($merchant)->get($customerId);

            return $got->getCustomer();
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error retrieving customer: {$e->getMessage()}");
        }
    }

    /**
     * Update a customer.
     *
     * @example Merchant mode
     * ```php
     * $input = new \Jamm\Request\UpdateCustomerInput('cus-123', $params);
     * $jamm->customer->update($input);
     * ```
     *
     * @example Platform mode
     * ```php
     * $input = new \Jamm\Request\UpdateCustomerInput('cus-123', $params, merchant: 'mer-merchant-123');
     * $jamm->customer->update($input);
     * ```
     *
     * @param Request\UpdateCustomerInput $input
     * @return V1UpdateCustomerResponse
     */
    public function update(Request\UpdateCustomerInput $input): V1UpdateCustomerResponse
    {
        try {
            return self::api($input->merchant)->update($input->customerId, $input->params);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error updating customer: {$e->getMessage()}");
        }
    }

    /**
     * Delete a customer.
     *
     * @example Merchant mode
     * ```php
     * $jamm->customer->delete('cus-123');
     * ```
     *
     * @example Platform mode
     * ```php
     * $jamm->customer->delete('cus-123', merchant: 'mer-merchant-123');
     * ```
     *
     * @param string $customerId
     * @param string|null $merchant Optional merchant ID for platform mode
     * @return V1DeleteCustomerResponse
     */
    public function delete(string $customerId, ?string $merchant = null): V1DeleteCustomerResponse
    {
        try {
            return self::api($merchant)->delete($customerId);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error deleting customer: {$e->getMessage()}");
        }
    }
}
