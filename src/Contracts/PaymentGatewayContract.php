<?php

namespace NepalPay\Gateway\Contracts;

use Illuminate\Http\Request;
use NepalPay\Gateway\DTO\PaymentInitiation;
use NepalPay\Gateway\DTO\PaymentResult;

interface PaymentGatewayContract
{
    /**
     * Start a new payment. Creates a transaction record and
     * returns data needed to redirect/render the payment form.
     *
     * $payload keys: amount, metadata (array), success_url, failure_url
     */
    public function initiate(array $payload): PaymentInitiation;

    /**
     * Handle the gateway's redirect back to our app after payment.
     */
    public function handleReturn(Request $request): PaymentResult;

    /**
     * Manually check a transaction's status with the gateway.
     */
    public function verify(string $transactionUuid): PaymentResult;
}