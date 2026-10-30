<?php

namespace NepalPay\Gateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NepalPay\Gateway\Models\PaymentTransaction;

class PaymentInitiated
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentTransaction $transaction
    ) {
    }
}