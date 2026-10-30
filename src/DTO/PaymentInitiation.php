<?php

namespace NepalPay\Gateway\DTO;

class PaymentInitiation
{
    public function __construct(
        public readonly string $transactionUuid,
        public readonly string $view,      // blade view name to render, e.g. 'nepal-payment::esewa-form'
        public readonly array $viewData,   // data to pass to that view (form fields, action url, etc.)
    ) {
    }
}