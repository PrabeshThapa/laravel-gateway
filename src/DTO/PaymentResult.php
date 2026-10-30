<?php

namespace NepalPay\Gateway\DTO;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,        // success|failed|pending
        public readonly string $transactionUuid,
        public readonly ?float $amount = null,
        public readonly array $raw = [],       // raw gateway response
        public readonly ?string $message = null,
    ) {
    }
}