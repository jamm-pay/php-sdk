<?php

namespace Jamm\Request;

class OffSessionInput
{
    /**
     * @param ?string $idempotencyKey Makes the charge retry-safe once enabled for your merchant. Not sent when null
     *                                or blank; unlike offSessionAsync, no key is generated.
     */
    public function __construct(
        public readonly string $customer,
        public readonly InitialCharge $charge,
        public readonly ?string $merchant = null,
        public readonly ?string $idempotencyKey = null,
    ) {}
}
