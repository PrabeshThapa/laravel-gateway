<?php

namespace NepalPay\Gateway;

use InvalidArgumentException;
use NepalPay\Gateway\Contracts\PaymentGatewayContract;
use NepalPay\Gateway\Drivers\EsewaDriver;
use NepalPay\Gateway\Drivers\FonepayDriver;
use NepalPay\Gateway\Drivers\KhaltiDriver;
use NepalPay\Gateway\Drivers\NepalPaymentDriver;
use NepalPay\Gateway\Models\GatewaySetting;

class GatewayManager
{
    protected array $drivers = [];

    public function driver(?string $name = null): PaymentGatewayContract
    {
        $name = $name ?? config('nepal-payment.default', 'esewa');

        $setting = GatewaySetting::where('gateway', $name)->first();

        if ($setting && !$setting->is_enabled) {
            throw new InvalidArgumentException("The payment gateway [{$name}] is currently disabled by the administrator.");
        }

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    protected function createDriver(string $name): PaymentGatewayContract
    {
        return match ($name) {
            'esewa' => new EsewaDriver(),
            'khalti' => new KhaltiDriver(),
            'nepal_payment' => new NepalPaymentDriver(),
            'fonepay' => new FonepayDriver(),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$name}]."),
        };
    }
}