<?php

namespace NepalPay\Gateway\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use NepalPay\Gateway\Contracts\PaymentGatewayContract;
use NepalPay\Gateway\DTO\PaymentInitiation;
use NepalPay\Gateway\DTO\PaymentResult;
use NepalPay\Gateway\Models\PaymentTransaction;

class KhaltiDriver implements PaymentGatewayContract
{
    protected array $config;

   public function __construct()
{
    $setting = \NepalPay\Gateway\Models\GatewaySetting::where('gateway', 'khalti')->first();
    $mode = $setting?->mode ?? config('nepal-payment.mode', 'dev');
    $dbCredentials = $setting?->getCredentials() ?? [];
    $fallbackConfig = config("nepal-payment.gateways.khalti.{$mode}", []);
    $this->config = array_merge($fallbackConfig, array_filter($dbCredentials));
}

    public function initiate(array $payload): PaymentInitiation
    {
        $amountNpr = (float) $payload['amount'];
        $amountPaisa = (int) round($amountNpr * 100);
        $purchaseOrderId = (string) \Illuminate\Support\Str::uuid();

        $returnUrl = route('nepal-payment.success', ['gateway' => 'khalti']);
        $websiteUrl = url('/');

        $transaction = PaymentTransaction::create([
            'gateway' => 'khalti',
            'transaction_uuid' => $purchaseOrderId,
            'amount' => $amountNpr,
            'status' => 'pending',
            'metadata' => $payload['metadata'] ?? [],
            'success_url' => $payload['success_url'] ?? null,
            'failure_url' => $payload['failure_url'] ?? null,
        ]);

        event(new \NepalPay\Gateway\Events\PaymentInitiated($transaction));

        $response = Http::withHeaders([
            'Authorization' => 'key ' . $this->config['secret_key'],
        ])->post($this->config['initiate_url'], [
            'return_url' => $returnUrl,
            'website_url' => $websiteUrl,
            'amount' => $amountPaisa,
            'purchase_order_id' => $purchaseOrderId,
            'purchase_order_name' => 'Order ' . $purchaseOrderId,
            'customer_info' => [
                'name' => $payload['metadata']['name'] ?? 'Test Customer',
                'email' => $payload['metadata']['email'] ?? 'test@example.com',
                'phone' => $payload['metadata']['phone'] ?? '9800000001',
            ],
        ]);

        $body = $response->json();

        if (!$response->successful() || !isset($body['payment_url'])) {
            $transaction->update([
                'status' => 'failed',
                'gateway_response' => $body,
            ]);

            return new PaymentInitiation(
                transactionUuid: $purchaseOrderId,
                view: 'nepal-payment::failure',
                viewData: [
                    'message' => $body['detail'] ?? 'Khalti initiation failed.',
                ],
            );
        }

        $transaction->update([
            'gateway_response' => $body,
        ]);

        // Khalti's pidx is needed later for lookup — store it in metadata
        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], ['pidx' => $body['pidx']]),
        ]);

        return new PaymentInitiation(
            transactionUuid: $purchaseOrderId,
            view: 'nepal-payment::redirect',
            viewData: [
                'redirect_url' => $body['payment_url'],
            ],
        );
    }

    public function handleReturn(Request $request): PaymentResult
    {
        $pidx = $request->query('pidx');

        if (!$pidx) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                transactionUuid: '',
                message: 'No pidx received from Khalti.',
            );
        }

        $result = $this->verifyByPidx($pidx);

        $transaction = PaymentTransaction::whereJsonContains('metadata->pidx', $pidx)->first();

        if ($transaction) {
    // Validate that the returned amount matches the expected order amount
    $isAmountValid = $result->amount !== null && (abs($result->amount - (float) $transaction->amount) < 0.01);
    $finalStatus = ($result->success && $isAmountValid) ? 'success' : 'failed';

    $transaction->update([
        'status' => $finalStatus,
        'gateway_response' => $result->raw,
    ]);

    if ($finalStatus === 'success') {
        event(new \NepalPay\Gateway\Events\PaymentSucceeded($transaction));
    } else {
        event(new \NepalPay\Gateway\Events\PaymentFailed($transaction));
    }

    return new PaymentResult(
        success: $finalStatus === 'success',
        status: $finalStatus,
        transactionUuid: $transaction->transaction_uuid,
        amount: $result->amount,
        raw: $result->raw,
        message: !$isAmountValid ? 'Paid amount does not match transaction amount.' : $result->message,
    );
}

            if ($result->success) {
                event(new \NepalPay\Gateway\Events\PaymentSucceeded($transaction));
            } else {
                event(new \NepalPay\Gateway\Events\PaymentFailed($transaction));
            }

            // Return a result carrying OUR transaction_uuid (not pidx) so the controller can look up success/failure urls
            return new PaymentResult(
                success: $result->success,
                status: $result->status,
                transactionUuid: $transaction->transaction_uuid,
                amount: $result->amount,
                raw: $result->raw,
                message: $result->message,
            );
        

        return $result;
    }

    public function verify(string $transactionUuid): PaymentResult
    {
        $transaction = PaymentTransaction::where('transaction_uuid', $transactionUuid)->first();
        $pidx = $transaction?->metadata['pidx'] ?? null;

        if (!$pidx) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                transactionUuid: $transactionUuid,
                message: 'No pidx found for this transaction.',
            );
        }

        return $this->verifyByPidx($pidx);
    }

    protected function verifyByPidx(string $pidx): PaymentResult
    {
        $response = Http::withHeaders([
            'Authorization' => 'key ' . $this->config['secret_key'],
        ])->post($this->config['lookup_url'], [
            'pidx' => $pidx,
        ]);

        $body = $response->json();
        $status = $body['status'] ?? 'Failed';

        return new PaymentResult(
            success: $status === 'Completed',
            status: $status === 'Completed' ? 'success' : 'failed',
            transactionUuid: $pidx,
            amount: isset($body['total_amount']) ? $body['total_amount'] / 100 : null,
            raw: $body ?? [],
        );
    }
}