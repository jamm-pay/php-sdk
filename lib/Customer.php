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
     * Get or create the OpenAPI client
     * @return CustomerApi
     * @throws ApiException
     */
    private static function api(): CustomerApi
    {
        $client = Config::get()->apiClient();

        return new CustomerApi($client->http, $client->conf);
    }

    /**
     * Create a new customer
     *
     * @param Request\Buyer $buyer
     * @return V1CreateCustomerResponse
     * @throws ApiException
     */
    public function create(Request\Buyer $buyer): V1CreateCustomerResponse
    {
        $request = new V1CreateCustomerRequest();
        $request->setBuyer($buyer);

        try {
            return self::api()->create($request);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error creating customer: {$e->getMessage()}");
        }
    }

    /**
     * Get a customer
     *
     * @param string $customerId
     * @return V1Customer|null
     * @throws ApiException
     */
    public function get(string $customerId): ?V1Customer
    {
        try {
            $got = self::api()->get($customerId);

            return $got->getCustomer();
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error retrieving customer: {$e->getMessage()}");
        }
    }

    /**
     * Update a customer
     *
     * @param string $customerId
     * @param Request\UpdateCustomerRequest $params
     * @return V1UpdateCustomerResponse
     * @throws ApiException
     */
    public function update(string $customerId, Request\UpdateCustomerRequest $params): V1UpdateCustomerResponse
    {
        try {
            return self::api()->update($customerId, $params);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error updating customer: {$e->getMessage()}");
        }
    }

    /**
     * Delete a customer
     *
     * @param string $customerId
     * @return V1DeleteCustomerResponse
     * @throws ApiException
     */
    public function delete(string $customerId): V1DeleteCustomerResponse
    {
        try {
            return self::api()->delete($customerId);
        } catch (\Exception $e) {
            throw new \Jamm\Exception\ApiException("Error deleting customer: {$e->getMessage()}");
        }
    }
}
