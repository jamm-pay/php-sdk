<?php

namespace Jamm\Request;

class UpdateCustomerInput
{
    public function __construct(
        public readonly string $customerId,
        public readonly UpdateCustomerRequest $params,
        public readonly ?string $merchant = null,
    ) {}
}
