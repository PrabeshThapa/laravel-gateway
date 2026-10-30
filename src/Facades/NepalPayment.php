<?php

namespace NepalPay\Gateway\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \NepalPay\Gateway\Contracts\PaymentGatewayContract driver(?string $name = null)
 */
class NepalPayment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'nepal-payment';
    }
}