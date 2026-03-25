<?php

namespace Jamm\Request;

class CreateCustomerInput
{
    public function __construct(
        public readonly Buyer $buyer,
        public readonly ?string $merchant = null,
    ) {}
}
