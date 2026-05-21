<?php

namespace Jamm\Request;

class OffSessionAsyncInput
{
    public function __construct(
        public readonly string $customer,
        public readonly InitialCharge $charge,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $merchant = null,
    ) {}
}
