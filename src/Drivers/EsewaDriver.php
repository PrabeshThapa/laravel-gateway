<?php

namespace NepalPay\Gateway\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use NepalPay\Gateway\Contracts\PaymentGatewayContract;
use NepalPay\Gateway\DTO\PaymentInitiation;
use NepalPay\Gateway\DTO\PaymentResult;
use NepalPay\Gateway\Models\PaymentTransaction;

class EsewaDriver implements PaymentGatewayContract
{
    protected array $config;

    public function __construct()
    {
        $setting = \NepalPay\Gateway\Models\GatewaySetting::where('gateway', 'esewa')->first();

        $mode = $setting?->mode ?? config('nepal-payment.mode', 'dev');
        $dbCredentials = $setting?->getCredentials() ?? [];

        $fallbackConfig = config("nepal-payment.gateways.esewa.{$mode}", []);

        // DB credentials take priority; fall back to .env-based config for any missing keys
        $this->config = array_merge($fallbackConfig, array_filter($dbCredentials));
    }
    public function initiate(array $payload): PaymentInitiation
    {
        $transactionUuid = (string) Str::uuid();
        $amount = number_format((float) $payload['amount'], 2, '.', '');
        $productCode = $this->config['merchant_code'];

        // eSewa v2 requires HMAC-SHA256 signature over these 3 fields, comma-joined values,
        // in the exact order: total_amount,transaction_uuid,product_code
        $signedFieldNames = 'total_amount,transaction_uuid,product_code';
        $message = "total_amount={$amount},transaction_uuid={$transactionUuid},product_code={$productCode}";
        $signature = base64_encode(hash_hmac('sha256', $message, $this->config['secret_key'], true));

        $transaction = PaymentTransaction::create([
            'gateway' => 'esewa',
            'transaction_uuid' => $transactionUuid,
            'amount' => $amount,
            'status' => 'pending',
            'metadata' => $payload['metadata'] ?? [],
            'success_url' => $payload['success_url'] ?? null,
            'failure_url' => $payload['failure_url'] ?? null,
        ]);

        event(new \NepalPay\Gateway\Events\PaymentInitiated($transaction));

        $successUrl = route('nepal-payment.success', ['gateway' => 'esewa']);
        $failureUrl = route('nepal-payment.failure', ['gateway' => 'esewa']);

        $fields = [
            'amount' => $amount,
            'tax_amount' => '0',
            'total_amount' => $amount,
            'transaction_uuid' => $transactionUuid,
            'product_code' => $productCode,
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'signed_field_names' => $signedFieldNames,
            'signature' => $signature,
        ];

        return new PaymentInitiation(
            transactionUuid: $transactionUuid,
            view: 'nepal-payment::esewa-form',
            viewData: [
                'action' => $this->config['form_url'],
                'fields' => $fields,
            ],
        );
    }

    public function handleReturn(Request $request): PaymentResult
    {
        // eSewa v2 sends a base64-encoded JSON string in "data" query param
        $encodedData = $request->query('data');

        if (!$encodedData) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                transactionUuid: '',
                message: 'No data received from eSewa.',
            );
        }

        $decoded = json_decode(base64_decode($encodedData), true);

        $transactionUuid = $decoded['transaction_uuid'] ?? null;
        $status = $decoded['status'] ?? 'FAILED';

        $transaction = PaymentTransaction::where('transaction_uuid', $transactionUuid)->first();

        if (!$transaction) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                transactionUuid: (string) $transactionUuid,
                message: 'Transaction not found.',
            );
        }

        // Verify with eSewa's status API to avoid trusting client-side redirect alone
        $verifyResult = $this->verify($transactionUuid);


        $transaction->update([
            'status' => $verifyResult->status,
            'gateway_response' => $decoded,
        ]);

        if ($verifyResult->success) {
            event(new \NepalPay\Gateway\Events\PaymentSucceeded($transaction));
        } else {
            event(new \NepalPay\Gateway\Events\PaymentFailed($transaction));
        }

        return $verifyResult;
    }

    public function verify(string $transactionUuid): PaymentResult
    {
        $transaction = PaymentTransaction::where('transaction_uuid', $transactionUuid)->first();

        $productCode = $this->config['merchant_code'];
        $amount = $transaction ? $transaction->amount : null;

        $response = Http::get($this->config['status_url'], [
            'product_code' => $productCode,
            'total_amount' => $amount,
            'transaction_uuid' => $transactionUuid,
        ]);

        if (!$response->successful()) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                transactionUuid: $transactionUuid,
                amount: $amount ? (float) $amount : null,
                raw: ['error' => 'eSewa status API request failed with status code ' . $response->status()],
                message: 'Failed to verify transaction with eSewa servers.',
            );
        }

        $body = $response->json() ?? [];
        $status = $body['status'] ?? 'FAILED';
        return new PaymentResult(
            success: $status === 'COMPLETE',
            status: $status === 'COMPLETE' ? 'success' : 'failed',
            transactionUuid: $transactionUuid,
            amount: $amount ? (float) $amount : null,
            raw: $body ?? [],
        );
    }
}
