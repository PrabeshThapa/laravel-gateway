<?php

namespace NepalPay\Gateway\View\Components;

use Illuminate\View\Component;
use NepalPay\Gateway\Models\GatewaySetting;

class Buttons extends Component
{
    public array $enabledGateways;

    protected array $labels = [
        'esewa' => 'Pay with eSewa',
        'khalti' => 'Pay with Khalti',
        'nepal_payment' => 'Pay with Nepal Payment',
        'fonepay' => 'Pay with Fonepay',
    ];

    protected array $allGateways = ['esewa', 'khalti', 'nepal_payment', 'fonepay'];

    public function __construct(
        public float|int|string $amount,
        public ?string $successUrl = null,
        public ?string $failureUrl = null,
        public array $metadata = [],
    ) {
        $settings = GatewaySetting::whereIn('gateway', $this->allGateways)->get()->keyBy('gateway');

        // A gateway is shown if: an explicit row exists AND is_enabled is true,
        // OR no row exists yet at all (defaults to enabled, matching GatewayManager's own default behavior).
        $this->enabledGateways = collect($this->allGateways)
            ->filter(function ($gateway) use ($settings) {
                $setting = $settings->get($gateway);
                return !$setting || $setting->is_enabled;
            })
            ->map(fn($gateway) => [
                'key' => $gateway,
                'label' => $this->labels[$gateway],
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('nepal-payment::components.buttons', [
            'enabledGateways' => $this->enabledGateways,
            'amount' => $this->amount,
            'successUrl' => $this->successUrl,
            'failureUrl' => $this->failureUrl,
            'metadata' => $this->metadata,
        ]);
    }
}