<?php

namespace Jamm\Request;

class OffSessionInput
{
    public function __construct(
        public readonly string $customer,
        public readonly InitialCharge $charge,
        public readonly ?string $merchant = null,
    ) {}
}
