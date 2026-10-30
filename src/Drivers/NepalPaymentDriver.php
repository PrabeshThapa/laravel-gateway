<?php

namespace NepalPay\Gateway\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use NepalPay\Gateway\Contracts\PaymentGatewayContract;
use NepalPay\Gateway\DTO\PaymentInitiation;
use NepalPay\Gateway\DTO\PaymentResult;
use NepalPay\Gateway\Models\PaymentTransaction;

class NepalPaymentDriver implements PaymentGatewayContract
{
    protected array $config;

    public function __construct()
{
    $setting = \NepalPay\Gateway\Models\GatewaySetting::where('gateway', 'nepal_payment')->first();
    $mode = $setting?->mode ?? config('nepal-payment.mode', 'dev');
    $dbCredentials = $setting?->getCredentials() ?? [];
    $fallbackConfig = config("nepal-payment.gateways.nepal_payment.{$mode}", []);
    $this->config = array_merge($fallbackConfig, array_filter($dbCredentials));
}

    public function initiate(array $payload): PaymentInitiation
    {
        $amount = number_format((float) $payload['amount'], 2, '.', '');
        $transactionUuid = (string) Str::uuid();

        $transaction = PaymentTransaction::create([
            'gateway' => 'nepal_payment',
            'transaction_uuid' => $transactionUuid,
            'amount' => $amount,
            'status' => 'pending',
            'metadata' => $payload['metadata'] ?? [],
            'success_url' => $payload['success_url'] ?? null,
            'failure_url' => $payload['failure_url'] ?? null,
        ]);

        event(new \NepalPay\Gateway\Events\PaymentInitiated($transaction));

        $processId = $this->getProcessId($amount, $transactionUuid);

        if (!$processId) {
            $transaction->update(['status' => 'failed']);

            return new PaymentInitiation(
                transactionUuid: $transactionUuid,
                view: 'nepal-payment::failure',
                viewData: [
                    'message' => 'Failed to initiate Nepal Payment transaction (ProcessId generation failed).',
                ],
            );
        }

        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], ['process_id' => $processId]),
        ]);

        $signature = $this->generateSignature($amount, $transactionUuid);

        return new PaymentInitiation(
            transactionUuid: $transactionUuid,
            view: 'nepal-payment::onepg-form',
            viewData: [
                'action' => $this->config['gateway_url'] . 'payment/index',
                'fields' => [
                    'MerchantId' => $this->config['merchant_id'],
                    'MerchantName' => $this->config['merchant_name'],
                    'MerchantTxnId' => $transactionUuid,
                    'Amount' => $amount,
                    'ProcessId' => $processId,
                    'Signature' => $signature,
                ],
            ],
        );
    }

    public function handleReturn(Request $request): PaymentResult
    {
        $transactionUuid = $request->input('MerchantTxnId') ?? $request->query('MerchantTxnId');

        if (!$transactionUuid) {
            return new PaymentResult(
                success: false,
                status: 'failed',
                transactionUuid: '',
                message: 'No MerchantTxnId received from Nepal Payment.',
            );
        }

        $result = $this->verify($transactionUuid);

        $transaction = PaymentTransaction::where('transaction_uuid', $transactionUuid)->first();

        if ($transaction) {
            $transaction->update([
                'status' => $result->status,
                'gateway_response' => $result->raw,
            ]);

            if ($result->success) {
                event(new \NepalPay\Gateway\Events\PaymentSucceeded($transaction));
            } else {
                event(new \NepalPay\Gateway\Events\PaymentFailed($transaction));
            }
        }

        return $result;
    }

    public function verify(string $transactionUuid): PaymentResult
    {
        $transaction = PaymentTransaction::where('transaction_uuid', $transactionUuid)->first();

        $signature = $this->generateSignature('', $transactionUuid);

        $response = Http::withBasicAuth($this->config['api_username'], $this->config['api_password'])
            ->asForm()
            ->post($this->config['api_url'] . 'CheckTransactionStatus', [
                'MerchantId' => $this->config['merchant_id'],
                'MerchantName' => $this->config['merchant_name'],
                'MerchantTxnId' => $transactionUuid,
                'Signature' => $signature,
            ]);

        $body = $response->json();
        $status = $body['data']['Status'] ?? 'Failed';

        return new PaymentResult(
            success: $status === 'Success',
            status: $status === 'Success' ? 'success' : 'failed',
            transactionUuid: $transactionUuid,
            amount: $transaction ? (float) $transaction->amount : null,
            raw: $body ?? [],
        );
    }

    protected function getProcessId(string $amount, string $transactionUuid): ?string
    {
        $signature = $this->generateSignature($amount, $transactionUuid);

        $response = Http::withBasicAuth($this->config['api_username'], $this->config['api_password'])
            ->asForm()
            ->withOptions(['debug' => false])
            ->post($this->config['api_url'] . 'GetProcessId', [
                'MerchantId' => $this->config['merchant_id'],
                'MerchantName' => $this->config['merchant_name'],
                'MerchantTxnId' => $transactionUuid,
                'Amount' => $amount,
                'Signature' => $signature,
            ]);

        $body = $response->json();

        if (($body['message'] ?? null) === 'Process Id generated successfully') {
            return $body['data']['ProcessId'] ?? null;
        }

        return null;
    }

    protected function generateSignature(string $amount, string $merchantTxnId): string
    {
        $str = $amount . $this->config['merchant_id'] . $this->config['merchant_name'] . $merchantTxnId;

        return hash_hmac('sha512', $str, $this->config['api_secret']);
    }
}