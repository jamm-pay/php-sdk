<?php

namespace Jamm\Request;

class OnSessionInput
{
    public function __construct(
        public readonly ?string $customer = null,
        public readonly bool $oneTime = false,
        public readonly ?Buyer $buyer = null,
        public readonly ?InitialCharge $charge = null,
        public readonly ?URL $redirect = null,
        public readonly ?string $merchant = null,
    ) {}
}
