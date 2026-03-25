<?php

namespace Jamm\Request;

class GetChargesInput
{
    public function __construct(
        public readonly string $customer,
        public readonly ?Pagination $pagination = null,
        public readonly ?string $merchant = null,
    ) {}
}
